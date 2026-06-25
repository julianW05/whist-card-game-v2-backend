<?php

namespace Database\Factories;

use App\Models\Round;
use App\Models\Trick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trick>
 */
class TrickFactory extends Factory
{
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'trick_number' => 1,
            'lead_suit' => null,
            'winner_id' => null,
        ];
    }
}
