<?php

namespace App\Services;

use App\Enums\RoundStatus;
use App\Events\BiddingComplete;
use App\Events\BidPlaced;
use App\Models\Bid;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BiddingService
{
    public function __construct(private DeckService $deckService) {}

    /**
     * @return Collection<int, GamePlayer>
     */
    public function biddingOrder(Game $game): Collection
    {
        $players = $game->players()->orderBy('seat_index')->get();
        $count = $players->count();

        return collect(range(1, $count))
            ->map(fn (int $offset): GamePlayer => $players[($game->dealer_index + $offset) % $count])
            ->values();
    }

    public function currentBidder(Round $round): ?GamePlayer
    {
        $bidUserIds = $round->bids()->pluck('user_id');

        return $this->biddingOrder($round->game)
            ->reject(fn (GamePlayer $player): bool => $bidUserIds->contains($player->user_id))
            ->first();
    }

    public function forbiddenBid(Round $round): ?int
    {
        $current = $this->currentBidder($round);

        if ($current === null || $current->seat_index !== $round->game->dealer_index) {
            return null;
        }

        $forbidden = $round->trick_count - (int) $round->bids()->sum('bid_amount');

        return $forbidden >= 0 && $forbidden <= $round->trick_count ? $forbidden : null;
    }

    public function placeBid(Round $round, User $user, int $amount): Bid
    {
        return DB::transaction(function () use ($round, $user, $amount): Bid {
            if ($round->status !== RoundStatus::Bidding) {
                throw ValidationException::withMessages([
                    'bid' => 'Bidding is not open for this round.',
                ]);
            }

            $current = $this->currentBidder($round);

            if ($current === null || $current->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'bid' => 'It is not your turn to bid.',
                ]);
            }

            if ($amount < 0 || $amount > $round->trick_count) {
                throw ValidationException::withMessages([
                    'bid' => "Bid must be between 0 and {$round->trick_count}.",
                ]);
            }

            if ($amount === $this->forbiddenBid($round)) {
                throw ValidationException::withMessages([
                    'bid' => 'This bid would make the total bids equal the trick count.',
                ]);
            }

            $bid = $round->bids()->create([
                'user_id' => $user->id,
                'bid_amount' => $amount,
                'tricks_won' => 0,
                'points_earned' => 0,
            ]);

            event(new BidPlaced($round->game));

            if ($this->currentBidder($round) === null) {
                $this->completeBidding($round);
                event(new BiddingComplete($round->game));
            }

            return $bid;
        });
    }

    private function completeBidding(Round $round): void
    {
        if ($round->is_final) {
            $this->deckService->deal($round);
        }

        $round->update(['status' => RoundStatus::Playing]);

        $round->tricks()->create([
            'trick_number' => 1,
            'lead_suit' => null,
            'winner_id' => null,
        ]);
    }
}
