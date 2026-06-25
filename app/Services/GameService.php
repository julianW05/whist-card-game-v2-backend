<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Events\GameStarted;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameService
{
    private const MIN_PLAYERS = 2;

    private const MAX_PLAYERS = 6;

    public function __construct(private RoundService $roundService) {}

    public function createGame(User $host, string $name, bool $isPublic = false): Game
    {
        return DB::transaction(function () use ($host, $name, $isPublic): Game {
            $game = Game::create([
                'code' => $this->generateCode(),
                'name' => $name,
                'host_id' => $host->id,
                'status' => GameStatus::Lobby,
                'is_public' => $isPublic,
                'player_count' => 1,
                'current_round' => 0,
                'dealer_index' => 0,
            ]);

            $game->players()->create([
                'user_id' => $host->id,
                'seat_index' => 0,
                'total_score' => 0,
            ]);

            return $game;
        });
    }

    public function joinGame(Game $game, User $user): GamePlayer
    {
        return DB::transaction(function () use ($game, $user): GamePlayer {
            if ($game->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages([
                    'game' => 'This game is no longer accepting players.',
                ]);
            }

            if ($game->players()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'game' => 'You have already joined this game.',
                ]);
            }

            if ($game->players()->count() >= self::MAX_PLAYERS) {
                throw ValidationException::withMessages([
                    'game' => 'This game is full.',
                ]);
            }

            $player = $game->players()->create([
                'user_id' => $user->id,
                'seat_index' => $game->players()->count(),
                'total_score' => 0,
            ]);

            $game->update(['player_count' => $game->players()->count()]);

            return $player;
        });
    }

    public function leaveGame(Game $game, User $user): void
    {
        DB::transaction(function () use ($game, $user): void {
            if ($game->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages([
                    'game' => 'You can only leave before the game starts.',
                ]);
            }

            if ($game->host_id === $user->id) {
                throw ValidationException::withMessages([
                    'game' => 'The host cannot leave the game.',
                ]);
            }

            $player = $game->players()->where('user_id', $user->id)->first();

            if ($player === null) {
                throw ValidationException::withMessages([
                    'game' => 'You are not in this game.',
                ]);
            }

            $player->delete();
            $this->resequenceSeats($game);
            $game->update(['player_count' => $game->players()->count()]);
        });
    }

    public function kickPlayer(Game $game, User $host, User $target): void
    {
        DB::transaction(function () use ($game, $host, $target): void {
            if ($game->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages([
                    'game' => 'Players can only be removed before the game starts.',
                ]);
            }

            if ($game->host_id !== $host->id) {
                throw ValidationException::withMessages([
                    'game' => 'Only the host can remove players.',
                ]);
            }

            if ($target->id === $host->id) {
                throw ValidationException::withMessages([
                    'game' => 'The host cannot be removed.',
                ]);
            }

            $player = $game->players()->where('user_id', $target->id)->first();

            if ($player === null) {
                throw ValidationException::withMessages([
                    'game' => 'That player is not in this game.',
                ]);
            }

            $player->delete();
            $this->resequenceSeats($game);
            $game->update(['player_count' => $game->players()->count()]);
        });
    }

    public function startGame(Game $game, User $host): Round
    {
        return DB::transaction(function () use ($game, $host): Round {
            if ($game->host_id !== $host->id) {
                throw ValidationException::withMessages([
                    'game' => 'Only the host can start the game.',
                ]);
            }

            if ($game->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages([
                    'game' => 'The game has already started.',
                ]);
            }

            if ($game->players()->count() < self::MIN_PLAYERS) {
                throw ValidationException::withMessages([
                    'game' => 'At least '.self::MIN_PLAYERS.' players are required to start.',
                ]);
            }

            $game->update(['status' => GameStatus::Active]);

            event(new GameStarted($game));

            return $this->roundService->startNextRound($game);
        });
    }

    /**
     * @return Collection<int, Game>
     */
    public function publicGames(): Collection
    {
        return Game::query()
            ->where('is_public', true)
            ->where('status', GameStatus::Lobby)
            ->withCount('players')
            ->latest()
            ->get();
    }

    private function generateCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (Game::query()->where('code', $code)->exists());

        return $code;
    }

    private function resequenceSeats(Game $game): void
    {
        $game->players()
            ->orderBy('seat_index')
            ->get()
            ->values()
            ->each(function (GamePlayer $player, int $index): void {
                if ($player->seat_index !== $index) {
                    $player->update(['seat_index' => $index]);
                }
            });
    }
}
