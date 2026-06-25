<?php

namespace App\Models;

use App\Enums\RoundStatus;
use App\Enums\Suit;
use Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'round_number', 'trick_count', 'trump_suit', 'is_final', 'status'])]
class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'trump_suit' => Suit::class,
            'is_final' => 'boolean',
            'status' => RoundStatus::class,
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function tricks(): HasMany
    {
        return $this->hasMany(Trick::class);
    }

    public function deck(): HasMany
    {
        return $this->hasMany(GameDeck::class);
    }
}
