<?php

namespace App\Services;

use App\Enums\RoundStatus;
use App\Models\Game;
use App\Models\Round;
use App\Models\Trick;
use App\Models\User;
use Illuminate\Support\Collection;

class GameStateService
{
    public function __construct(
        private DeckService $deckService,
        private TrickService $trickService,
    ) {}

    /**
     * @return array{game: Game, round: ?Round, trick: ?Trick, hand: Collection}
     */
    public function build(Game $game, ?User $user): array
    {
        $round = $game->rounds()->orderByDesc('round_number')->first();

        $trick = $round?->status === RoundStatus::Playing
            ? $this->trickService->currentTrick($round)
            : null;

        $hand = $round !== null && $user !== null
            ? $this->deckService->getHand($round, $user)
            : new Collection;

        $game->load(['players.user', 'rounds.bids']);
        $round?->load('bids');
        $trick?->load('cards.gameDeck.card');

        return [
            'game' => $game,
            'round' => $round,
            'trick' => $trick,
            'hand' => $hand,
        ];
    }
}
