<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'host_id' => $this->host_id,
            'status' => $this->status->value,
            'is_public' => $this->is_public,
            'player_count' => $this->player_count,
            'current_round' => $this->current_round,
            'dealer_index' => $this->dealer_index,
            'players' => PlayerResource::collection(
                $this->whenLoaded('players', fn () => $this->players->sortBy('seat_index')->values())
            ),
        ];
    }
}
