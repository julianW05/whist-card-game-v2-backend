<?php

namespace App\Services;

use App\Enums\DeckCardStatus;
use App\Enums\GameStatus;
use App\Enums\RoundStatus;
use App\Events\CardPlayed;
use App\Events\GameComplete;
use App\Events\RoundComplete;
use App\Events\TrickComplete;
use App\Models\GameDeck;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\Trick;
use App\Models\TrickCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrickService
{
    public function __construct(
        private DeckService $deckService,
        private ScoringService $scoringService,
    ) {}

    public function currentTrick(Round $round): ?Trick
    {
        return $round->tricks()
            ->whereNull('winner_id')
            ->orderBy('trick_number')
            ->first();
    }

    public function currentPlayer(Trick $trick): ?GamePlayer
    {
        if ($trick->winner_id !== null) {
            return null;
        }

        $players = $this->seatedPlayers($trick->round);
        $leadOffset = $players->search(fn (GamePlayer $player): bool => $player->id === $this->leadPlayer($trick)->id);

        return $players[($leadOffset + $trick->cards()->count()) % $players->count()];
    }

    public function playCard(Trick $trick, User $user, GameDeck $gameDeckEntry): TrickCard
    {
        return DB::transaction(function () use ($trick, $user, $gameDeckEntry): TrickCard {
            if ($trick->round->status !== RoundStatus::Playing || $trick->winner_id !== null) {
                throw ValidationException::withMessages([
                    'card' => 'This trick is not open for play.',
                ]);
            }

            $current = $this->currentPlayer($trick);

            if ($current === null || $current->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'card' => 'It is not your turn to play.',
                ]);
            }

            if (
                $gameDeckEntry->round_id !== $trick->round_id
                || $gameDeckEntry->held_by !== $user->id
                || $gameDeckEntry->status !== DeckCardStatus::InHand
            ) {
                throw ValidationException::withMessages([
                    'card' => 'That card is not in your hand.',
                ]);
            }

            $playedSuit = $gameDeckEntry->card->suit;

            if ($trick->lead_suit !== null && $playedSuit !== $trick->lead_suit) {
                $canFollow = $this->deckService->getHand($trick->round, $user)
                    ->contains(fn (GameDeck $entry): bool => $entry->card->suit === $trick->lead_suit);

                if ($canFollow) {
                    throw ValidationException::withMessages([
                        'card' => 'You must follow the lead suit.',
                    ]);
                }
            }

            $playOrder = $trick->cards()->count();

            if ($playOrder === 0) {
                $trick->update(['lead_suit' => $playedSuit]);
            }

            $trickCard = $trick->cards()->create([
                'user_id' => $user->id,
                'game_deck_id' => $gameDeckEntry->id,
                'play_order' => $playOrder,
            ]);

            $this->deckService->playCard($gameDeckEntry);

            event(new CardPlayed($trick->round->game));

            if ($trick->cards()->count() === $this->seatedPlayers($trick->round)->count()) {
                $this->completeTrick($trick);
            }

            return $trickCard;
        });
    }

    private function completeTrick(Trick $trick): void
    {
        $round = $trick->round;
        $game = $round->game;

        $winner = $this->determineWinningCard($trick);
        $trick->update(['winner_id' => $winner->user_id]);

        event(new TrickComplete($game));

        if ($trick->trick_number < $round->trick_count) {
            $round->tricks()->create([
                'trick_number' => $trick->trick_number + 1,
                'lead_suit' => null,
                'winner_id' => null,
            ]);

            return;
        }

        $round->update(['status' => RoundStatus::Complete]);
        $this->scoringService->scoreRound($round);

        event(new RoundComplete($game));

        if ($round->is_final) {
            $game->update(['status' => GameStatus::Finished]);
            event(new GameComplete($game));
        }
    }

    private function determineWinningCard(Trick $trick): TrickCard
    {
        $cards = $trick->cards()->with('gameDeck.card')->orderBy('play_order')->get();
        $trump = $trick->round->trump_suit;

        $contenders = $cards->filter(fn (TrickCard $card): bool => $card->gameDeck->card->suit === $trump);

        if ($contenders->isEmpty()) {
            $contenders = $cards->filter(fn (TrickCard $card): bool => $card->gameDeck->card->suit === $trick->lead_suit);
        }

        $highest = $contenders->max(fn (TrickCard $card): int => $card->gameDeck->card->sort_order);

        return $contenders
            ->filter(fn (TrickCard $card): bool => $card->gameDeck->card->sort_order === $highest)
            ->sortBy('play_order')
            ->first();
    }

    private function leadPlayer(Trick $trick): GamePlayer
    {
        $players = $this->seatedPlayers($trick->round);

        $firstCard = $trick->cards()->orderBy('play_order')->first();

        if ($firstCard !== null) {
            return $players->firstWhere('user_id', $firstCard->user_id);
        }

        if ($trick->trick_number === 1) {
            return $players[($trick->round->game->dealer_index + 1) % $players->count()];
        }

        $previous = $trick->round->tricks()
            ->where('trick_number', $trick->trick_number - 1)
            ->first();

        return $players->firstWhere('user_id', $previous->winner_id);
    }

    /**
     * @return Collection<int, GamePlayer>
     */
    private function seatedPlayers(Round $round): Collection
    {
        return $round->game->players()->orderBy('seat_index')->get();
    }
}
