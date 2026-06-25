<?php

namespace Tests\Feature;

use App\Enums\CardValue;
use App\Enums\DeckCardStatus;
use App\Enums\GameStatus;
use App\Enums\RoundStatus;
use App\Enums\Suit;
use App\Events\BiddingComplete;
use App\Events\BidPlaced;
use App\Events\CardPlayed;
use App\Events\GameComplete;
use App\Events\GameStarted;
use App\Events\RoundComplete;
use App\Events\RoundStarted;
use App\Events\TrickComplete;
use App\Models\Card;
use App\Models\Game;
use App\Models\GameDeck;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\User;
use App\Services\BiddingService;
use App\Services\DeckService;
use App\Services\GameService;
use App\Services\TrickService;
use Database\Seeders\CardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    private GameService $games;

    private BiddingService $bidding;

    private TrickService $tricks;

    private DeckService $deck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CardSeeder::class);
        $this->games = app(GameService::class);
        $this->bidding = app(BiddingService::class);
        $this->tricks = app(TrickService::class);
        $this->deck = app(DeckService::class);
    }

    private function startedGame(): array
    {
        $host = User::factory()->create();
        $game = $this->games->createGame($host, 'Game');
        $member = User::factory()->create();
        $this->games->joinGame($game, $member);
        $round = $this->games->startGame($game, $host);

        return [$game, $host, $member, $round];
    }

    private function handCard(Round $round, GamePlayer $player, Suit $suit, CardValue $value): GameDeck
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

    public function test_starting_a_game_broadcasts_game_started_and_round_started(): void
    {
        $host = User::factory()->create();
        $game = $this->games->createGame($host, 'Game');
        $this->games->joinGame($game, User::factory()->create());

        Event::fake([GameStarted::class, RoundStarted::class]);

        $this->games->startGame($game, $host);

        Event::assertDispatched(GameStarted::class);
        Event::assertDispatched(RoundStarted::class);
    }

    public function test_each_bid_broadcasts_and_the_last_completes_bidding(): void
    {
        [$game, $host, $member, $round] = $this->startedGame();

        Event::fake([BidPlaced::class, BiddingComplete::class]);

        $this->bidding->placeBid($round, $member, 0);
        $this->bidding->placeBid($round, $host, 0);

        Event::assertDispatchedTimes(BidPlaced::class, 2);
        Event::assertDispatchedTimes(BiddingComplete::class, 1);
    }

    public function test_playing_a_round_broadcasts_card_trick_and_round_events(): void
    {
        [$game, $host, $member, $round] = $this->startedGame();
        $this->bidding->placeBid($round, $member, 0);
        $this->bidding->placeBid($round, $host, 0);
        $trick = $this->tricks->currentTrick($round->fresh());

        Event::fake([CardPlayed::class, TrickComplete::class, RoundComplete::class, GameComplete::class]);

        $this->tricks->playCard($trick, $member, $this->deck->getHand($round, $member)->first());
        $this->tricks->playCard($trick->fresh(), $host, $this->deck->getHand($round, $host)->first());

        Event::assertDispatchedTimes(CardPlayed::class, 2);
        Event::assertDispatchedTimes(TrickComplete::class, 1);
        Event::assertDispatchedTimes(RoundComplete::class, 1);
        Event::assertNotDispatched(GameComplete::class);
    }

    public function test_finishing_the_final_round_broadcasts_game_complete(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active, 'player_count' => 2, 'dealer_index' => 0]);
        $host = User::factory()->create();
        $member = User::factory()->create();
        $hostPlayer = GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $host->id, 'seat_index' => 0]);
        $memberPlayer = GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $member->id, 'seat_index' => 1]);

        $round = Round::factory()->create([
            'game_id' => $game->id,
            'round_number' => 24,
            'trick_count' => 1,
            'is_final' => true,
            'trump_suit' => Suit::Spades,
            'status' => RoundStatus::Playing,
        ]);
        $trick = $round->tricks()->create(['trick_number' => 1, 'lead_suit' => null, 'winner_id' => null]);

        $memberCard = $this->handCard($round, $memberPlayer, Suit::Hearts, CardValue::Nine);
        $hostCard = $this->handCard($round, $hostPlayer, Suit::Hearts, CardValue::King);

        Event::fake([RoundComplete::class, GameComplete::class]);

        $this->tricks->playCard($trick, $member, $memberCard);
        $this->tricks->playCard($trick->fresh(), $host, $hostCard);

        Event::assertDispatched(RoundComplete::class);
        Event::assertDispatched(GameComplete::class);
        $this->assertSame(GameStatus::Finished, $game->fresh()->status);
    }

    public function test_events_broadcast_on_the_presence_channel_without_leaking_hands(): void
    {
        [$game] = $this->startedGame();

        $event = new GameStarted($game);

        $this->assertSame('presence-game.'.$game->id, $event->broadcastOn()->name);
        $this->assertSame('GameStarted', $event->broadcastAs());

        $payload = $event->broadcastWith();

        $this->assertSame($game->id, $payload['game']['id']);
        $this->assertArrayHasKey('scoreboard', $payload);
        $this->assertSame([], $payload['hand']);
    }

    public function test_presence_channel_only_authorizes_participants(): void
    {
        [$game, $host, $member] = $this->startedGame();
        $outsider = User::factory()->create();

        $callback = $this->channelCallback('game.{game}');

        $this->assertSame(['id' => $host->id, 'name' => $host->name], $callback($host, $game));
        $this->assertSame(['id' => $member->id, 'name' => $member->name], $callback($member, $game));
        $this->assertNull($callback($outsider, $game));
    }

    private function channelCallback(string $pattern): callable
    {
        $broadcaster = Broadcast::connection();
        $property = new \ReflectionProperty($broadcaster, 'channels');
        $property->setAccessible(true);

        return $property->getValue($broadcaster)[$pattern];
    }
}
