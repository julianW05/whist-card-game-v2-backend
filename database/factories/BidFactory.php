<?php

namespace Database\Factories;

use App\Models\Bid;
use App\Models\Round;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bid>
 */
class BidFactory extends Factory
{
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'user_id' => User::factory(),
            'bid_amount' => 0,
            'tricks_won' => 0,
            'points_earned' => 0,
        ];
    }
}
