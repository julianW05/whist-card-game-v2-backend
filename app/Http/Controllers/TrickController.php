<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlayCardRequest;
use App\Http\Resources\GameStateResource;
use App\Models\GameDeck;
use App\Models\Trick;
use App\Services\GameStateService;
use App\Services\TrickService;

class TrickController extends Controller
{
    public function __construct(
        private TrickService $trickService,
        private GameStateService $stateService,
    ) {}

    public function play(PlayCardRequest $request, Trick $trick): GameStateResource
    {
        $gameDeckEntry = GameDeck::query()->findOrFail($request->integer('game_deck_id'));

        $this->trickService->playCard($trick, $request->user(), $gameDeckEntry);

        return GameStateResource::make($this->stateService->build($trick->round->game, $request->user()));
    }
}
