<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'name' => $this->whenLoaded('user', fn (): string => $this->user->name),
            'seat_index' => $this->seat_index,
            'total_score' => $this->total_score,
        ];
    }
}
