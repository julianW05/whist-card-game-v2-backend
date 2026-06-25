<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'suit' => $this->suit->value,
            'value' => $this->value->value,
            'sort_order' => $this->sort_order,
            'image_path' => $this->image_path,
            'label' => $this->label,
        ];
    }
}
