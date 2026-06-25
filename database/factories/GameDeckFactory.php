<?php

namespace Database\Factories;

use App\Enums\DeckCardStatus;
use App\Models\Card;
use App\Models\Game;
use App\Models\GameDeck;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameDeck>
 */
class GameDeckFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'round_id' => Round::factory(),
            'card_id' => Card::factory(),
            'position' => 0,
            'status' => DeckCardStatus::InDeck,
            'held_by' => null,
        ];
    }
}
