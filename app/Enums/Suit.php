<?php

namespace App\Enums;

enum Suit: string
{
    case Clubs = 'clubs';
    case Hearts = 'hearts';
    case Spades = 'spades';
    case Diamonds = 'diamonds';

    public static function forRound(int $roundNumber): self
    {
        return [self::Clubs, self::Hearts, self::Spades, self::Diamonds][($roundNumber - 1) % 4];
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Clubs => '♣',
            self::Hearts => '♥',
            self::Spades => '♠',
            self::Diamonds => '♦',
        };
    }

    public function initial(): string
    {
        return match ($this) {
            self::Clubs => 'C',
            self::Hearts => 'H',
            self::Spades => 'S',
            self::Diamonds => 'D',
        };
    }
}
