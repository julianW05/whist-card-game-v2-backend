<?php

namespace Database\Factories;

use App\Models\GameDeck;
use App\Models\Trick;
use App\Models\TrickCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrickCard>
 */
class TrickCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trick_id' => Trick::factory(),
            'user_id' => User::factory(),
            'game_deck_id' => GameDeck::factory(),
            'play_order' => 0,
        ];
    }
}
