<?php

namespace App\Services;

use App\Enums\RoundStatus;
use App\Enums\Suit;
use App\Events\RoundStarted;
use App\Models\Game;
use App\Models\Round;
use Illuminate\Support\Facades\DB;

class RoundService
{
    public function __construct(private DeckService $deckService) {}

    public function peakTrickCount(int $playerCount): int
    {
        return min(12, intdiv(48, $playerCount));
    }

    public function totalRounds(int $playerCount): int
    {
        return $this->peakTrickCount($playerCount) * 2;
    }

    public function isFinalRound(int $roundNumber, int $playerCount): bool
    {
        return $roundNumber === $this->totalRounds($playerCount);
    }

    public function trickCountForRound(int $roundNumber, int $playerCount): int
    {
        $peak = $this->peakTrickCount($playerCount);

        if ($this->isFinalRound($roundNumber, $playerCount)) {
            return $peak;
        }

        return $roundNumber <= $peak ? $roundNumber : (2 * $peak - $roundNumber);
    }

    public function startNextRound(Game $game): Round
    {
        return DB::transaction(function () use ($game) {
            $playerCount = $game->players()->count();
            $roundNumber = $game->current_round + 1;
            $isFinal = $this->isFinalRound($roundNumber, $playerCount);

            $round = $game->rounds()->create([
                'round_number' => $roundNumber,
                'trick_count' => $this->trickCountForRound($roundNumber, $playerCount),
                'trump_suit' => Suit::forRound($roundNumber),
                'is_final' => $isFinal,
                'status' => RoundStatus::Bidding,
            ]);

            $game->update([
                'current_round' => $roundNumber,
                'dealer_index' => $roundNumber === 1
                    ? $game->dealer_index
                    : ($game->dealer_index + 1) % $playerCount,
            ]);

            $this->deckService->generateForRound($round);

            if (! $isFinal) {
                $this->deckService->deal($round);
            }

            event(new RoundStarted($game));

            return $round;
        });
    }
}
