<?php

namespace App\Models;

use App\Enums\CardValue;
use App\Enums\Suit;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['suit', 'value', 'sort_order', 'image_path', 'label', 'copy'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'suit' => Suit::class,
            'value' => CardValue::class,
        ];
    }
}
