<?php

namespace App\Enums;

enum RoundStatus: string
{
    case Bidding = 'bidding';
    case Playing = 'playing';
    case Complete = 'complete';
}
