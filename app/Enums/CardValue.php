<?php

namespace App\Enums;

enum CardValue: string
{
    case Nine = '9';
    case Ten = '10';
    case Jack = 'J';
    case Queen = 'Q';
    case King = 'K';
    case Ace = 'A';

    public function sortOrder(): int
    {
        return match ($this) {
            self::Nine => 1,
            self::Ten => 2,
            self::Jack => 3,
            self::Queen => 4,
            self::King => 5,
            self::Ace => 6,
        };
    }

    public function fullName(): string
    {
        return match ($this) {
            self::Nine => 'Nine',
            self::Ten => 'Ten',
            self::Jack => 'Jack',
            self::Queen => 'Queen',
            self::King => 'King',
            self::Ace => 'Ace',
        };
    }
}
