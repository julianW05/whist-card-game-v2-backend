<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\RoundStatus;
use App\Models\User;
use App\Services\GameService;
use Database\Seeders\CardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GameServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameService $gameService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CardSeeder::class);
        $this->gameService = app(GameService::class);
    }

    public function test_creating_a_game_seats_the_host_first(): void
    {
        $host = User::factory()->create();

        $game = $this->gameService->createGame($host, 'Family Night', isPublic: true);

        $this->assertSame(GameStatus::Lobby, $game->status);
        $this->assertSame(6, strlen($game->code));
        $this->assertTrue($game->is_public);
        $this->assertSame(1, $game->player_count);
        $this->assertSame($host->id, $game->players()->where('seat_index', 0)->first()->user_id);
    }

    public function test_created_games_receive_distinct_codes(): void
    {
        $host = User::factory()->create();

        $first = $this->gameService->createGame($host, 'One');
        $second = $this->gameService->createGame($host, 'Two');

        $this->assertNotSame($first->code, $second->code);
    }

    public function test_joining_assigns_the_next_seat_and_updates_the_count(): void
    {
        $game = $this->gameService->createGame(User::factory()->create(), 'Game');

        $second = $this->gameService->joinGame($game, User::factory()->create());
        $third = $this->gameService->joinGame($game, User::factory()->create());

        $this->assertSame(1, $second->seat_index);
        $this->assertSame(2, $third->seat_index);
        $this->assertSame(3, $game->fresh()->player_count);
    }

    public function test_a_player_cannot_join_the_same_game_twice(): void
    {
        $game = $this->gameService->createGame(User::factory()->create(), 'Game');
        $user = User::factory()->create();
        $this->gameService->joinGame($game, $user);

        $this->expectException(ValidationException::class);

        $this->gameService->joinGame($game, $user);
    }

    public function test_a_full_game_rejects_further_players(): void
    {
        $game = $this->gameService->createGame(User::factory()->create(), 'Game');

        foreach (range(1, 5) as $ignored) {
            $this->gameService->joinGame($game, User::factory()->create());
        }

        $this->expectException(ValidationException::class);

        $this->gameService->joinGame($game, User::factory()->create());
    }

    public function test_cannot_join_a_game_that_has_started(): void
    {
        $game = $this->gameService->createGame(User::factory()->create(), 'Game');
        $game->update(['status' => GameStatus::Active]);

        $this->expectException(ValidationException::class);

        $this->gameService->joinGame($game, User::factory()->create());
    }

    public function test_leaving_resequences_the_remaining_seats(): void
    {
        $host = User::factory()->create();
        $game = $this->gameService->createGame($host, 'Game');
        $leaver = User::factory()->create();
        $this->gameService->joinGame($game, $leaver);
        $stayer = User::factory()->create();
        $this->gameService->joinGame($game, $stayer);

        $this->gameService->leaveGame($game, $leaver);

        $this->assertSame(2, $game->fresh()->player_count);
        $this->assertSame([0, 1], $game->players()->orderBy('seat_index')->pluck('seat_index')->all());
        $this->assertSame(1, $game->players()->where('user_id', $stayer->id)->first()->seat_index);
    }

    public function test_the_host_cannot_leave_the_game(): void
    {
        $host = User::factory()->create();
        $game = $this->gameService->createGame($host, 'Game');
        $this->gameService->joinGame($game, User::factory()->create());

        $this->expectException(ValidationException::class);

        $this->gameService->leaveGame($game, $host);
    }

    public function test_the_host_can_kick_a_player(): void
    {
        $host = User::factory()->create();
        $game = $this->gameService->createGame($host, 'Game');
        $target = User::factory()->create();
        $this->gameService->joinGame($game, $target);
        $other = User::factory()->create();
        $this->gameService->joinGame($game, $other);

        $this->gameService->kickPlayer($game, $host, $target);

        $this->assertSame(2, $game->fresh()->player_count);
        $this->assertNull($game->players()->where('user_id', $target->id)->first());
        $this->assertSame([0, 1], $game->players()->orderBy('seat_index')->pluck('seat_index')->all());
    }

    public function test_only_the_host_can_kick(): void
    {
        $host = User::factory()->create();
        $game = $this->gameService->createGame($host, 'Game');
        $member = User::factory()->create();
        $this->gameService->joinGame($game, $member);
        $target = User::factory()->create();
        $this->gameService->joinGame($game, $target);

        $this->expectException(ValidationException::class);

        $this->gameService->kickPlayer($game, $member, $target);
    }

    public function test_the_host_cannot_be_kicked(): void
    {
        $host = User::factory()->create();
        $game = $this->gameService->createGame($host, 'Game');
        $this->gameService->joinGame($game, User::factory()->create());

        $this->expectException(ValidationException::class);

        $this->gameService->kickPlayer($game, $host, $host);
    }

    public function test_only_the_host_can_start_the_game(): void
    {
        $host = User::factory()->create();
        $game = $this->gameService->createGame($host, 'Game');
        $member = User::factory()->create();
        $this->gameService->joinGame($game, $member);

        $this->expectException(ValidationException::class);

        $this->gameService->startGame($game, $member);
    }

    public function test_starting_requires_at_least_two_players(): void
    {
        $host = User::factory()->create();
        $game = $this->gameService->createGame($host, 'Game');

        $this->expectException(ValidationException::class);

        $this->gameService->startGame($game, $host);
    }

    public function test_starting_activates_the_game_and_opens_the_first_round(): void
    {
        $host = User::factory()->create();
        $game = $this->gameService->createGame($host, 'Game');
        $this->gameService->joinGame($game, User::factory()->create());
        $this->gameService->joinGame($game, User::factory()->create());

        $round = $this->gameService->startGame($game, $host);

        $this->assertSame(GameStatus::Active, $game->fresh()->status);
        $this->assertSame(1, $round->round_number);
        $this->assertSame(RoundStatus::Bidding, $round->status);
        $this->assertSame(48, $round->deck()->count());
        $this->assertSame(1, $game->fresh()->current_round);
    }

    public function test_public_games_lists_only_open_public_lobbies(): void
    {
        $host = User::factory()->create();
        $this->gameService->createGame($host, 'Public Open', isPublic: true);
        $this->gameService->createGame($host, 'Private', isPublic: false);
        $started = $this->gameService->createGame($host, 'Public Started', isPublic: true);
        $started->update(['status' => GameStatus::Active]);

        $games = $this->gameService->publicGames();

        $this->assertCount(1, $games);
        $this->assertSame('Public Open', $games->first()->name);
        $this->assertSame(1, $games->first()->players_count);
    }
}
