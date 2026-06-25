<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(6)),
            'name' => fake()->streetName(),
            'host_id' => User::factory(),
            'status' => GameStatus::Lobby,
            'is_public' => false,
            'player_count' => 0,
            'current_round' => 0,
            'dealer_index' => 0,
        ];
    }
}
