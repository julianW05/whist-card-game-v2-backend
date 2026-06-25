<?php

namespace App\Http\Resources;

use App\Enums\RoundStatus;
use App\Models\GamePlayer;
use App\Models\Round;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScoreboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $players = $this->players->sortBy('seat_index')->values();

        $rounds = $this->rounds
            ->where('status', RoundStatus::Complete)
            ->sortBy('round_number')
            ->values();

        $cumulative = $players->mapWithKeys(fn (GamePlayer $player): array => [$player->user_id => 0])->all();

        $rows = $rounds->map(function (Round $round) use ($players, &$cumulative): array {
            $bids = $round->bids->keyBy('user_id');

            $cells = $players->map(function (GamePlayer $player) use ($bids, &$cumulative): array {
                $bid = $bids->get($player->user_id);
                $cumulative[$player->user_id] += $bid?->points_earned ?? 0;

                return [
                    'user_id' => $player->user_id,
                    'bid' => $bid?->bid_amount,
                    'tricks_won' => $bid?->tricks_won,
                    'score' => $cumulative[$player->user_id],
                ];
            })->all();

            return [
                'round_number' => $round->round_number,
                'trump_suit' => $round->trump_suit->value,
                'trump_symbol' => $round->trump_suit->symbol(),
                'is_final' => $round->is_final,
                'leader_id' => collect($cumulative)->sortDesc()->keys()->first(),
                'cells' => $cells,
            ];
        })->all();

        return [
            'players' => PlayerResource::collection($players),
            'rounds' => $rows,
        ];
    }
}
