<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use App\Http\Resources\GameResource;
use App\Http\Resources\GameStateResource;
use App\Http\Resources\HandResource;
use App\Models\Game;
use App\Models\User;
use App\Services\GameService;
use App\Services\GameStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GameController extends Controller
{
    public function __construct(
        private GameService $gameService,
        private GameStateService $stateService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return GameResource::collection($this->gameService->publicGames($request->user()));
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        return GameResource::collection($this->gameService->gamesForUser($request->user()));
    }

    public function store(StoreGameRequest $request): JsonResponse
    {
        $game = $this->gameService->createGame(
            $request->user(),
            $request->validated('name'),
            $request->boolean('is_public'),
        );

        return GameResource::make($game->load('players.user'))
            ->response()
            ->setStatusCode(201);
    }

    public function join(Request $request, Game $game): GameResource
    {
        $this->gameService->joinGame($game, $request->user());

        return GameResource::make($game->fresh()->load('players.user'));
    }

    public function start(Request $request, Game $game): GameStateResource
    {
        $this->gameService->startGame($game, $request->user());

        return GameStateResource::make($this->stateService->build($game->fresh(), $request->user()));
    }

    public function kick(Request $request, Game $game, User $user): GameResource
    {
        $this->gameService->kickPlayer($game, $request->user(), $user);

        return GameResource::make($game->fresh()->load('players.user'));
    }

    public function leave(Request $request, Game $game): Response
    {
        $this->gameService->leaveGame($game, $request->user());

        return response()->noContent();
    }

    public function state(Request $request, Game $game): GameStateResource
    {
        $this->authorizeParticipant($game, $request->user());

        return GameStateResource::make($this->stateService->build($game, $request->user()));
    }

    public function sync(Request $request, Game $game): GameStateResource
    {
        $this->authorizeParticipant($game, $request->user());

        $this->gameService->markReconnected($game, $request->user());

        return GameStateResource::make($this->stateService->build($game, $request->user()));
    }

    public function hand(Request $request, Game $game): AnonymousResourceCollection
    {
        $this->authorizeParticipant($game, $request->user());

        return HandResource::collection($this->stateService->build($game, $request->user())['hand']);
    }

    private function authorizeParticipant(Game $game, User $user): void
    {
        abort_unless($game->players()->where('user_id', $user->id)->exists(), 403);
    }
}
