<?php

namespace Database\Factories;

use App\Enums\CardValue;
use App\Enums\Suit;
use App\Models\Card;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    public function definition(): array
    {
        $suit = fake()->randomElement(Suit::cases());
        $value = fake()->randomElement(CardValue::cases());

        return [
            'suit' => $suit,
            'value' => $value,
            'sort_order' => $value->sortOrder(),
            'image_path' => 'cards/'.$value->value.$suit->initial().'.svg',
            'label' => $value->fullName().' of '.Str::ucfirst($suit->value),
            'copy' => fake()->randomElement([1, 2]),
        ];
    }
}
