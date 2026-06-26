<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use App\Services\GameService;
use Database\Seeders\CardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameApiTest extends TestCase
{
    use RefreshDatabase;

    private GameService $gameService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CardSeeder::class);
        $this->gameService = app(GameService::class);
    }

    private function hostedGame(User $host, bool $isPublic = false): Game
    {
        return $this->gameService->createGame($host, 'Test Game', $isPublic);
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/games')->assertUnauthorized();
    }

    public function test_a_user_can_create_a_game(): void
    {
        Sanctum::actingAs($host = User::factory()->create());

        $response = $this->postJson('/api/games', ['name' => 'Friday Night', 'is_public' => true]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Friday Night')
            ->assertJsonPath('data.player_count', 1)
            ->assertJsonPath('data.players.0.user_id', $host->id);
    }

    public function test_public_games_are_listed(): void
    {
        $host = User::factory()->create();
        $this->hostedGame($host, isPublic: true);
        $this->hostedGame($host, isPublic: false);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/games')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_public_games_exclude_games_the_user_is_in(): void
    {
        $host = User::factory()->create();
        $joined = $this->hostedGame($host, isPublic: true);
        $this->hostedGame($host, isPublic: true);

        Sanctum::actingAs($user = User::factory()->create());
        $this->gameService->joinGame($joined, $user);

        $this->getJson('/api/games')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_user_can_list_their_games(): void
    {
        $user = User::factory()->create();
        $this->hostedGame($user, isPublic: false);

        $host = User::factory()->create();
        $active = $this->hostedGame($host, isPublic: true);
        $this->gameService->joinGame($active, $user);
        $this->gameService->startGame($active, $host);

        $this->hostedGame(User::factory()->create(), isPublic: true);

        Sanctum::actingAs($user);

        $this->getJson('/api/games/mine')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_user_can_join_by_code(): void
    {
        $game = $this->hostedGame(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/games/{$game->code}/join")
            ->assertOk()
            ->assertJsonPath('data.player_count', 2);
    }

    public function test_the_host_can_start_the_game(): void
    {
        $host = User::factory()->create();
        $game = $this->hostedGame($host);
        $this->gameService->joinGame($game, User::factory()->create());

        Sanctum::actingAs($host);

        $this->postJson("/api/games/{$game->id}/start")
            ->assertOk()
            ->assertJsonPath('data.game.status', 'active')
            ->assertJsonPath('data.round.round_number', 1)
            ->assertJsonPath('data.round.status', 'bidding');
    }

    public function test_a_non_host_cannot_start_the_game(): void
    {
        $host = User::factory()->create();
        $game = $this->hostedGame($host);
        $member = User::factory()->create();
        $this->gameService->joinGame($game, $member);

        Sanctum::actingAs($member);

        $this->postJson("/api/games/{$game->id}/start")->assertStatus(422);
    }

    public function test_the_host_can_kick_a_player(): void
    {
        $host = User::factory()->create();
        $game = $this->hostedGame($host);
        $target = User::factory()->create();
        $this->gameService->joinGame($game, $target);

        Sanctum::actingAs($host);

        $this->postJson("/api/games/{$game->id}/kick/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.player_count', 1);
    }

    public function test_a_player_can_leave_the_lobby(): void
    {
        $host = User::factory()->create();
        $game = $this->hostedGame($host);
        $member = User::factory()->create();
        $this->gameService->joinGame($game, $member);

        Sanctum::actingAs($member);

        $this->postJson("/api/games/{$game->id}/leave")->assertNoContent();
        $this->assertSame(1, $game->fresh()->player_count);
    }

    public function test_non_participants_cannot_view_game_state(): void
    {
        $game = $this->hostedGame(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/games/{$game->id}/state")->assertForbidden();
    }

    public function test_a_participant_can_sync_on_reconnect(): void
    {
        $host = User::factory()->create();
        $game = $this->hostedGame($host);
        $this->gameService->joinGame($game, User::factory()->create());

        Sanctum::actingAs($host);
        $this->postJson("/api/games/{$game->id}/start")->assertOk();

        $this->postJson("/api/games/{$game->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.game.id', $game->id);
    }

    public function test_non_participants_cannot_sync(): void
    {
        $game = $this->hostedGame(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/games/{$game->id}/sync")->assertForbidden();
    }

    public function test_a_full_round_can_be_played_through_the_api(): void
    {
        $host = User::factory()->create();
        $game = $this->hostedGame($host);
        $member = User::factory()->create();
        $this->gameService->joinGame($game, $member);

        Sanctum::actingAs($host);
        $roundId = $this->postJson("/api/games/{$game->id}/start")->json('data.round.id');

        $this->actingAsJson($member)->postJson("/api/rounds/{$roundId}/bid", ['amount' => 0])->assertOk();
        $this->actingAsJson($host)->postJson("/api/rounds/{$roundId}/bid", ['amount' => 0])->assertOk();

        $trickId = $this->actingAsJson($member)->getJson("/api/games/{$game->id}/state")->json('data.trick.id');

        $memberCard = $this->actingAsJson($member)->getJson("/api/games/{$game->id}/hand")->json('data.0.id');
        $this->actingAsJson($member)->postJson("/api/tricks/{$trickId}/play", ['game_deck_id' => $memberCard])->assertOk();

        $hostCard = $this->actingAsJson($host)->getJson("/api/games/{$game->id}/hand")->json('data.0.id');
        $this->actingAsJson($host)->postJson("/api/tricks/{$trickId}/play", ['game_deck_id' => $hostCard])->assertOk();

        $state = $this->actingAsJson($host)->getJson("/api/games/{$game->id}/state");

        $state->assertJsonPath('data.round.status', 'complete')
            ->assertJsonPath('data.trick', null);
    }

    public function test_you_cannot_bid_out_of_turn_through_the_api(): void
    {
        $host = User::factory()->create();
        $game = $this->hostedGame($host);
        $member = User::factory()->create();
        $this->gameService->joinGame($game, $member);

        Sanctum::actingAs($host);
        $roundId = $this->postJson("/api/games/{$game->id}/start")->json('data.round.id');

        Sanctum::actingAs($host);
        $this->postJson("/api/rounds/{$roundId}/bid", ['amount' => 0])->assertStatus(422);
    }

    private function actingAsJson(User $user): self
    {
        Sanctum::actingAs($user);

        return $this;
    }
}
