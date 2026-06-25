<?php

namespace App\Enums;

enum DeckCardStatus: string
{
    case InDeck = 'in_deck';
    case InHand = 'in_hand';
    case Discarded = 'discarded';
}
