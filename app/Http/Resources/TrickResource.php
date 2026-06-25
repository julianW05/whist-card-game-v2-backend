<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrickResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trick_number' => $this->trick_number,
            'lead_suit' => $this->lead_suit?->value,
            'winner_id' => $this->winner_id,
            'cards' => TrickCardResource::collection($this->whenLoaded('cards')),
        ];
    }
}
