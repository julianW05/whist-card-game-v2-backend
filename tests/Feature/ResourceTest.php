<?php

namespace Tests\Feature;

use App\Enums\CardValue;
use App\Enums\DeckCardStatus;
use App\Enums\GameStatus;
use App\Enums\RoundStatus;
use App\Enums\Suit;
use App\Http\Resources\CardResource;
use App\Http\Resources\GameResource;
use App\Http\Resources\GameStateResource;
use App\Http\Resources\HandResource;
use App\Http\Resources\PlayerResource;
use App\Http\Resources\RoundResource;
use App\Http\Resources\ScoreboardResource;
use App\Http\Resources\TrickResource;
use App\Models\Bid;
use App\Models\Card;
use App\Models\Game;
use App\Models\GameDeck;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\Trick;
use App\Models\TrickCard;
use App\Models\User;
use Database\Seeders\CardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\JsonResource;
use Tests\TestCase;

class ResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CardSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function arr(JsonResource $resource): array
    {
        return json_decode(json_encode($resource), true);
    }

    private function card(Suit $suit, CardValue $value, int $copy = 1): Card
    {
        return Card::where('suit', $suit)->where('value', $value)->where('copy', $copy)->firstOrFail();
    }

    private function handCard(Round $round, GamePlayer $player, Suit $suit, CardValue $value): GameDeck
    {
        return GameDeck::factory()->create([
            'game_id' => $round->game_id,
            'round_id' => $round->id,
            'card_id' => $this->card($suit, $value)->id,
            'position' => $round->deck()->count(),
            'status' => DeckCardStatus::InHand,
            'held_by' => $player->user_id,
        ]);
    }

    public function test_card_resource_exposes_the_card_fields(): void
    {
        $card = $this->card(Suit::Hearts, CardValue::Ace);

        $data = $this->arr(new CardResource($card));

        $this->assertSame('hearts', $data['suit']);
        $this->assertSame('A', $data['value']);
        $this->assertSame(6, $data['sort_order']);
        $this->assertSame('cards/AH.svg', $data['image_path']);
    }

    public function test_player_resource_includes_the_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Ada']);
        $player = GamePlayer::factory()->create(['user_id' => $user->id, 'seat_index' => 0, 'total_score' => 7]);

        $data = $this->arr(new PlayerResource($player->load('user')));

        $this->assertSame('Ada', $data['name']);
        $this->assertSame(7, $data['total_score']);
    }

    public function test_hand_resource_exposes_the_deck_id_and_card(): void
    {
        $round = Round::factory()->create();
        $player = GamePlayer::factory()->create(['game_id' => $round->game_id, 'seat_index' => 0]);
        $entry = $this->handCard($round, $player, Suit::Spades, CardValue::King);

        $data = $this->arr(new HandResource($entry));

        $this->assertSame($entry->id, $data['id']);
        $this->assertSame('spades', $data['card']['suit']);
        $this->assertSame('K', $data['card']['value']);
    }

    public function test_round_resource_includes_the_trump_symbol_and_bids(): void
    {
        $round = Round::factory()->create(['trump_suit' => Suit::Hearts, 'status' => RoundStatus::Bidding]);
        Bid::factory()->create(['round_id' => $round->id, 'bid_amount' => 3]);

        $data = $this->arr(new RoundResource($round->load('bids')));

        $this->assertSame('hearts', $data['trump_suit']);
        $this->assertSame('♥', $data['trump_symbol']);
        $this->assertSame('bidding', $data['status']);
        $this->assertCount(1, $data['bids']);
        $this->assertSame(3, $data['bids'][0]['bid_amount']);
    }

    public function test_trick_resource_includes_the_played_cards(): void
    {
        $round = Round::factory()->create(['trump_suit' => Suit::Clubs]);
        $trick = Trick::factory()->create(['round_id' => $round->id, 'lead_suit' => Suit::Hearts, 'trick_number' => 1]);
        $player = GamePlayer::factory()->create(['game_id' => $round->game_id, 'seat_index' => 0]);
        $entry = $this->handCard($round, $player, Suit::Hearts, CardValue::Nine);
        TrickCard::factory()->create([
            'trick_id' => $trick->id,
            'user_id' => $player->user_id,
            'game_deck_id' => $entry->id,
            'play_order' => 0,
        ]);

        $data = $this->arr(new TrickResource($trick->load('cards.gameDeck.card')));

        $this->assertSame('hearts', $data['lead_suit']);
        $this->assertCount(1, $data['cards']);
        $this->assertSame('9', $data['cards'][0]['card']['value']);
    }

    public function test_game_resource_lists_players_sorted_by_seat(): void
    {
        $game = Game::factory()->create();
        $second = User::factory()->create(['name' => 'Second']);
        $first = User::factory()->create(['name' => 'First']);
        GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $second->id, 'seat_index' => 1]);
        GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $first->id, 'seat_index' => 0]);

        $data = $this->arr(new GameResource($game->load('players.user')));

        $this->assertSame(['First', 'Second'], array_column($data['players'], 'name'));
    }

    public function test_scoreboard_accumulates_scores_and_marks_the_leader(): void
    {
        $game = Game::factory()->create();
        $ada = User::factory()->create(['name' => 'Ada']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $adaPlayer = GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $ada->id, 'seat_index' => 0]);
        $bobPlayer = GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $bob->id, 'seat_index' => 1]);

        $roundOne = Round::factory()->create(['game_id' => $game->id, 'round_number' => 1, 'trump_suit' => Suit::Clubs, 'status' => RoundStatus::Complete]);
        Bid::factory()->create(['round_id' => $roundOne->id, 'user_id' => $ada->id, 'bid_amount' => 1, 'tricks_won' => 1, 'points_earned' => 11]);
        Bid::factory()->create(['round_id' => $roundOne->id, 'user_id' => $bob->id, 'bid_amount' => 1, 'tricks_won' => 0, 'points_earned' => -1]);

        $roundTwo = Round::factory()->create(['game_id' => $game->id, 'round_number' => 2, 'trump_suit' => Suit::Hearts, 'status' => RoundStatus::Complete]);
        Bid::factory()->create(['round_id' => $roundTwo->id, 'user_id' => $ada->id, 'bid_amount' => 0, 'tricks_won' => 1, 'points_earned' => -1]);
        Bid::factory()->create(['round_id' => $roundTwo->id, 'user_id' => $bob->id, 'bid_amount' => 2, 'tricks_won' => 2, 'points_earned' => 12]);

        $data = $this->arr(new ScoreboardResource($game->load('players.user', 'rounds.bids')));

        $this->assertCount(2, $data['rounds']);

        $rowOne = $data['rounds'][0];
        $this->assertSame(11, $rowOne['cells'][0]['score']);
        $this->assertSame(-1, $rowOne['cells'][1]['score']);
        $this->assertSame($ada->id, $rowOne['leader_id']);

        $rowTwo = $data['rounds'][1];
        $this->assertSame(10, $rowTwo['cells'][0]['score']);
        $this->assertSame(11, $rowTwo['cells'][1]['score']);
        $this->assertSame($bob->id, $rowTwo['leader_id']);
    }

    public function test_game_state_resource_composes_the_pieces_with_a_private_hand(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $user = User::factory()->create();
        $player = GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => $user->id, 'seat_index' => 0]);
        $round = Round::factory()->create(['game_id' => $game->id, 'status' => RoundStatus::Playing]);
        $trick = Trick::factory()->create(['round_id' => $round->id, 'trick_number' => 1]);
        $hand = collect([
            $this->handCard($round, $player, Suit::Hearts, CardValue::Nine),
            $this->handCard($round, $player, Suit::Spades, CardValue::Ace),
        ]);

        $state = [
            'game' => $game->load('players.user', 'rounds.bids'),
            'round' => $round->load('bids'),
            'trick' => $trick->load('cards.gameDeck.card'),
            'hand' => $hand,
        ];

        $data = $this->arr(new GameStateResource($state));

        $this->assertSame($game->id, $data['game']['id']);
        $this->assertSame($round->id, $data['round']['id']);
        $this->assertSame($trick->id, $data['trick']['id']);
        $this->assertCount(2, $data['hand']);
        $this->assertArrayHasKey('scoreboard', $data);
    }

    public function test_game_state_resource_tolerates_a_missing_round_and_trick(): void
    {
        $game = Game::factory()->create();
        GamePlayer::factory()->create(['game_id' => $game->id, 'user_id' => User::factory()->create()->id, 'seat_index' => 0]);

        $state = [
            'game' => $game->load('players.user', 'rounds.bids'),
            'round' => null,
            'trick' => null,
            'hand' => null,
        ];

        $data = $this->arr(new GameStateResource($state));

        $this->assertNull($data['round']);
        $this->assertNull($data['trick']);
        $this->assertSame([], $data['hand']);
    }
}
