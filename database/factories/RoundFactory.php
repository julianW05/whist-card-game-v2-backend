<?php

namespace Database\Factories;

use App\Enums\RoundStatus;
use App\Enums\Suit;
use App\Models\Game;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'round_number' => 1,
            'trick_count' => 1,
            'trump_suit' => Suit::Clubs,
            'is_final' => false,
            'status' => RoundStatus::Bidding,
        ];
    }
}
