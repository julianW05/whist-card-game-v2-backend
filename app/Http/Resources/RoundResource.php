<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'round_number' => $this->round_number,
            'trick_count' => $this->trick_count,
            'trump_suit' => $this->trump_suit->value,
            'trump_symbol' => $this->trump_suit->symbol(),
            'is_final' => $this->is_final,
            'status' => $this->status->value,
            'scoreboard_revealed' => (bool) $this->scoreboard_revealed,
            'bids' => BidResource::collection($this->whenLoaded('bids')),
        ];
    }
}
