<?php

namespace App\Services;

use App\Enums\RoundStatus;
use App\Models\Game;
use App\Models\Round;
use App\Models\Trick;
use App\Models\User;
use Illuminate\Support\Collection;

class GameStateService
{
    public function __construct(
        private DeckService $deckService,
        private TrickService $trickService,
        private BiddingService $biddingService,
    ) {}

    /**
     * @return array{game: Game, round: ?Round, trick: ?Trick, hand: Collection, turn: array{current_bidder_id: ?int, forbidden_bid: ?int, current_player_id: ?int}, tricks_won: array<int, int>}
     */
    public function build(Game $game, ?User $user): array
    {
        $round = $game->rounds()->orderByDesc('round_number')->first();

        $trick = $round?->status === RoundStatus::Playing
            ? $this->trickService->currentTrick($round)
            : null;

        $hand = $round !== null && $user !== null
            ? $this->deckService->getHand($round, $user)
            : new Collection;

        $tricksWon = $round !== null
            ? $round->tricks()
                ->whereNotNull('winner_id')
                ->get()
                ->groupBy('winner_id')
                ->map(fn (Collection $tricks): int => $tricks->count())
                ->all()
            : [];

        $turn = [
            'current_bidder_id' => $round?->status === RoundStatus::Bidding
                ? $this->biddingService->currentBidder($round)?->user_id
                : null,
            'forbidden_bid' => $round?->status === RoundStatus::Bidding
                ? $this->biddingService->forbiddenBid($round)
                : null,
            'current_player_id' => $trick !== null
                ? $this->trickService->currentPlayer($trick)?->user_id
                : null,
        ];

        $game->load(['players.user', 'rounds.bids']);
        $round?->load('bids');
        $trick?->load('cards.gameDeck.card');

        return [
            'game' => $game,
            'round' => $round,
            'trick' => $trick,
            'hand' => $hand,
            'turn' => $turn,
            'tricks_won' => $tricksWon,
        ];
    }
}
