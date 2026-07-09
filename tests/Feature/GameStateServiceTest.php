<?php

namespace Tests\Feature;

use App\Enums\CardValue;
use App\Enums\DeckCardStatus;
use App\Enums\RoundStatus;
use App\Enums\Suit;
use App\Models\Card;
use App\Models\Game;
use App\Models\GameDeck;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\User;
use App\Services\GameStateService;
use App\Services\TrickService;
use Database\Seeders\CardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameStateServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameStateService $stateService;

    private TrickService $trickService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CardSeeder::class);
        $this->stateService = app(GameStateService::class);
        $this->trickService = app(TrickService::class);
    }

    private function playingRound(int $trickCount): Round
    {
        $game = Game::factory()->create(['player_count' => 2, 'dealer_index' => 0]);

        foreach (range(0, 1) as $seat) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'user_id' => User::factory()->create()->id,
                'seat_index' => $seat,
            ]);
        }

        $round = Round::factory()->create([
            'game_id' => $game->id,
            'trick_count' => $trickCount,
            'trump_suit' => Suit::Spades,
            'status' => RoundStatus::Playing,
        ]);

        $round->tricks()->create(['trick_number' => 1, 'lead_suit' => null, 'winner_id' => null]);

        return $round;
    }

    private function giveCard(Round $round, GamePlayer $player, Suit $suit, CardValue $value): GameDeck
    {
        $card = Card::where('suit', $suit)->where('value', $value)->where('copy', 1)->firstOrFail();

        return GameDeck::factory()->create([
            'game_id' => $round->game_id,
            'round_id' => $round->id,
            'card_id' => $card->id,
            'position' => $round->deck()->count(),
            'status' => DeckCardStatus::InHand,
            'held_by' => $player->user_id,
        ]);
    }

    public function test_completed_trick_stays_in_last_trick_while_the_next_trick_opens(): void
    {
        $round = $this->playingRound(trickCount: 2);
        $leader = $round->game->players()->where('seat_index', 1)->first();
        $dealer = $round->game->players()->where('seat_index', 0)->first();
        $trick = $this->trickService->currentTrick($round);

        $this->trickService->playCard($trick, $leader->user, $this->giveCard($round, $leader, Suit::Hearts, CardValue::King));
        $this->trickService->playCard($trick->fresh(), $dealer->user, $this->giveCard($round, $dealer, Suit::Hearts, CardValue::Nine));

        $state = $this->stateService->build($round->game->fresh(), null);

        $this->assertSame(RoundStatus::Playing, $state['round']->status);
        $this->assertSame(2, $state['trick']->trick_number);
        $this->assertCount(0, $state['trick']->cards);
        $this->assertNotNull($state['last_trick']);
        $this->assertSame(1, $state['last_trick']->trick_number);
        $this->assertCount(2, $state['last_trick']->cards);
        $this->assertSame($leader->user_id, $state['last_trick']->winner_id);
    }

    public function test_round_complete_keeps_the_final_trick_as_last_trick(): void
    {
        $round = $this->playingRound(trickCount: 1);
        $leader = $round->game->players()->where('seat_index', 1)->first();
        $dealer = $round->game->players()->where('seat_index', 0)->first();
        $trick = $this->trickService->currentTrick($round);

        $this->trickService->playCard($trick, $leader->user, $this->giveCard($round, $leader, Suit::Hearts, CardValue::King));
        $this->trickService->playCard($trick->fresh(), $dealer->user, $this->giveCard($round, $dealer, Suit::Hearts, CardValue::Nine));

        $state = $this->stateService->build($round->game->fresh(), null);

        $this->assertSame(RoundStatus::Complete, $state['round']->status);
        $this->assertNull($state['trick']);
        $this->assertNotNull($state['last_trick']);
        $this->assertCount(2, $state['last_trick']->cards);
        $this->assertSame($leader->user_id, $state['last_trick']->winner_id);
    }
}
