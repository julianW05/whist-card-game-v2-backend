<?php

namespace App\Models;

use App\Enums\DeckCardStatus;
use Database\Factories\GameDeckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'round_id', 'card_id', 'position', 'status', 'held_by'])]
class GameDeck extends Model
{
    /** @use HasFactory<GameDeckFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => DeckCardStatus::class,
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'held_by');
    }
}
