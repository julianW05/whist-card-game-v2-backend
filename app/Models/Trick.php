<?php

namespace App\Models;

use App\Enums\Suit;
use Database\Factories\TrickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['round_id', 'trick_number', 'lead_suit', 'winner_id'])]
class Trick extends Model
{
    /** @use HasFactory<TrickFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'lead_suit' => Suit::class,
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(TrickCard::class);
    }
}
