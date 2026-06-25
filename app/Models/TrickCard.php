<?php

namespace App\Models;

use Database\Factories\TrickCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trick_id', 'user_id', 'game_deck_id', 'play_order'])]
class TrickCard extends Model
{
    /** @use HasFactory<TrickCardFactory> */
    use HasFactory;

    public function trick(): BelongsTo
    {
        return $this->belongsTo(Trick::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gameDeck(): BelongsTo
    {
        return $this->belongsTo(GameDeck::class);
    }
}
