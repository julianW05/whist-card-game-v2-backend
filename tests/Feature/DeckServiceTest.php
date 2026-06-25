<?php

namespace Tests\Feature;

use App\Enums\DeckCardStatus;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\User;
use App\Services\DeckService;
use Database\Seeders\CardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeckService $deckService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CardSeeder::class);
        $this->deckService = new DeckService;
    }

    private function makeRound(int $playerCount, int $trickCount): Round
    {
        $game = Game::factory()->create(['player_count' => $playerCount]);

        foreach (range(0, $playerCount - 1) as $seat) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'user_id' => User::factory()->create()->id,
                'seat_index' => $seat,
            ]);
        }

        return Round::factory()->create([
            'game_id' => $game->id,
            'trick_count' => $trickCount,
        ]);
    }

    public function test_generate_for_round_creates_a_full_shuffled_deck(): void
    {
        $round = $this->makeRound(playerCount: 4, trickCount: 5);

        $this->deckService->generateForRound($round);

        $deck = $round->deck()->get();

        $this->assertCount(48, $deck);
        $this->assertSame(range(0, 47), $deck->pluck('position')->sort()->values()->all());
        $this->assertTrue($deck->every(fn ($entry) => $entry->status === DeckCardStatus::InDeck));
        $this->assertTrue($deck->every(fn ($entry) => $entry->held_by === null));
    }

    public function test_deal_gives_each_player_trick_count_cards(): void
    {
        $round = $this->makeRound(playerCount: 4, trickCount: 5);
        $this->deckService->generateForRound($round);

        $this->deckService->deal($round);

        $players = $round->game->players()->get();

        foreach ($players as $player) {
            $this->assertSame(
                5,
                $round->deck()->where('held_by', $player->user_id)->where('status', DeckCardStatus::InHand)->count(),
            );
        }

        $this->assertSame(20, $round->deck()->where('status', DeckCardStatus::InHand)->count());
        $this->assertSame(28, $round->deck()->where('status', DeckCardStatus::InDeck)->count());
    }

    public function test_get_hand_returns_only_the_users_in_hand_cards(): void
    {
        $round = $this->makeRound(playerCount: 3, trickCount: 4);
        $this->deckService->generateForRound($round);
        $this->deckService->deal($round);

        $user = $round->game->players()->orderBy('seat_index')->first()->user;

        $hand = $this->deckService->getHand($round, $user);

        $this->assertCount(4, $hand);
        $this->assertTrue($hand->every(fn ($entry) => $entry->held_by === $user->id));
        $this->assertTrue($hand->every(fn ($entry) => $entry->relationLoaded('card')));
    }

    public function test_play_card_discards_the_card_and_clears_the_holder(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 3);
        $this->deckService->generateForRound($round);
        $this->deckService->deal($round);

        $user = $round->game->players()->orderBy('seat_index')->first()->user;
        $card = $this->deckService->getHand($round, $user)->first();

        $this->deckService->playCard($card);

        $card->refresh();
        $this->assertSame(DeckCardStatus::Discarded, $card->status);
        $this->assertNull($card->held_by);
    }
}
