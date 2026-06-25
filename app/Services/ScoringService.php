<?php

namespace App\Services;

use App\Models\Round;
use App\Models\Trick;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    public function scoreRound(Round $round): void
    {
        DB::transaction(function () use ($round): void {
            $tricksWon = $round->tricks()
                ->whereNotNull('winner_id')
                ->get()
                ->countBy(fn (Trick $trick): int => $trick->winner_id);

            foreach ($round->bids()->get() as $bid) {
                $won = $tricksWon->get($bid->user_id, 0);
                $points = $won === $bid->bid_amount ? 10 + $bid->bid_amount : -1;

                $bid->update([
                    'tricks_won' => $won,
                    'points_earned' => $points,
                ]);

                $round->game->players()
                    ->where('user_id', $bid->user_id)
                    ->increment('total_score', $points);
            }
        });
    }
}
