<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class GameStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->resource;

        return [
            'game' => new GameResource($state['game']),
            'round' => $state['round'] ? new RoundResource($state['round']) : null,
            'trick' => $state['trick'] ? new TrickResource($state['trick']) : null,
            'hand' => HandResource::collection($state['hand'] ?? new Collection),
            'scoreboard' => new ScoreboardResource($state['game']),
        ];
    }
}
