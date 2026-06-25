<?php

namespace Database\Seeders;

use App\Enums\CardValue;
use App\Enums\Suit;
use App\Models\Card;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CardSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Suit::cases() as $suit) {
            foreach (CardValue::cases() as $value) {
                foreach ([1, 2] as $copy) {
                    Card::updateOrCreate(
                        ['suit' => $suit, 'value' => $value, 'copy' => $copy],
                        [
                            'sort_order' => $value->sortOrder(),
                            'image_path' => 'cards/'.$value->value.$suit->initial().'.svg',
                            'label' => $value->fullName().' of '.Str::ucfirst($suit->value),
                        ],
                    );
                }
            }
        }
    }
}
