<?php

namespace App\Http\Controllers;

use App\Enums\RoundStatus;
use App\Http\Requests\PlaceBidRequest;
use App\Http\Resources\GameStateResource;
use App\Models\Round;
use App\Services\BiddingService;
use App\Services\GameStateService;
use App\Services\RoundService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoundController extends Controller
{
    public function __construct(
        private BiddingService $biddingService,
        private RoundService $roundService,
        private GameStateService $stateService,
    ) {}

    public function bid(PlaceBidRequest $request, Round $round): GameStateResource
    {
        $this->biddingService->placeBid($round, $request->user(), $request->integer('amount'));

        return GameStateResource::make($this->stateService->build($round->game, $request->user()));
    }

    public function revealScoreboard(Request $request, Round $round): GameStateResource
    {
        $game = $round->game;

        abort_unless($game->host_id === $request->user()->id, 403);

        if ($round->status !== RoundStatus::Complete) {
            throw ValidationException::withMessages([
                'round' => 'The round must be complete before opening the scoreboard.',
            ]);
        }

        $this->roundService->revealScoreboard($round);

        return GameStateResource::make($this->stateService->build($game->fresh(), $request->user()));
    }

    public function startNext(Request $request, Round $round): GameStateResource
    {
        $game = $round->game;

        abort_unless($game->host_id === $request->user()->id, 403);

        if ($round->status !== RoundStatus::Complete) {
            throw ValidationException::withMessages([
                'round' => 'The current round must finish before the next one begins.',
            ]);
        }

        $this->roundService->startNextRound($game);

        return GameStateResource::make($this->stateService->build($game->fresh(), $request->user()));
    }
}
