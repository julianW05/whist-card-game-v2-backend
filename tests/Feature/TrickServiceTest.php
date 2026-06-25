<?php

namespace Tests\Feature;

use App\Enums\CardValue;
use App\Enums\DeckCardStatus;
use App\Enums\RoundStatus;
use App\Enums\Suit;
use App\Models\Bid;
use App\Models\Card;
use App\Models\Game;
use App\Models\GameDeck;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\User;
use App\Services\TrickService;
use Database\Seeders\CardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrickServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrickService $trickService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CardSeeder::class);
        $this->trickService = app(TrickService::class);
    }

    private function makePlayingRound(int $playerCount, int $trickCount, Suit $trump, int $dealerIndex = 0): Round
    {
        $game = Game::factory()->create([
            'player_count' => $playerCount,
            'dealer_index' => $dealerIndex,
        ]);

        foreach (range(0, $playerCount - 1) as $seat) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'user_id' => User::factory()->create()->id,
                'seat_index' => $seat,
            ]);
        }

        $round = Round::factory()->create([
            'game_id' => $game->id,
            'trick_count' => $trickCount,
            'trump_suit' => $trump,
            'status' => RoundStatus::Playing,
        ]);

        $round->tricks()->create(['trick_number' => 1, 'lead_suit' => null, 'winner_id' => null]);

        return $round;
    }

    private function player(Round $round, int $seat): GamePlayer
    {
        return $round->game->players()->where('seat_index', $seat)->first();
    }

    private function giveCard(Round $round, GamePlayer $player, Suit $suit, CardValue $value, int $copy = 1): GameDeck
    {
        $card = Card::where('suit', $suit)->where('value', $value)->where('copy', $copy)->firstOrFail();

        return GameDeck::factory()->create([
            'game_id' => $round->game_id,
            'round_id' => $round->id,
            'card_id' => $card->id,
            'position' => $round->deck()->count(),
            'status' => DeckCardStatus::InHand,
            'held_by' => $player->user_id,
        ]);
    }

    public function test_first_trick_is_led_by_the_player_left_of_the_dealer(): void
    {
        $round = $this->makePlayingRound(playerCount: 3, trickCount: 2, trump: Suit::Spades, dealerIndex: 1);
        $trick = $this->trickService->currentTrick($round);

        $this->assertSame(2, $this->trickService->currentPlayer($trick)->seat_index);
    }

    public function test_playing_a_card_sets_lead_suit_discards_it_and_advances_the_turn(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $card = $this->giveCard($round, $this->player($round, 1), Suit::Hearts, CardValue::Nine);

        $trickCard = $this->trickService->playCard($trick, $this->player($round, 1)->user, $card);

        $this->assertSame(0, $trickCard->play_order);
        $this->assertSame(Suit::Hearts, $trick->fresh()->lead_suit);
        $this->assertSame(DeckCardStatus::Discarded, $card->fresh()->status);
        $this->assertSame(0, $this->trickService->currentPlayer($trick->fresh())->seat_index);
    }

    public function test_a_player_must_follow_the_lead_suit_when_able(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $lead = $this->giveCard($round, $this->player($round, 1), Suit::Hearts, CardValue::Nine);
        $this->trickService->playCard($trick, $this->player($round, 1)->user, $lead);

        $this->giveCard($round, $this->player($round, 0), Suit::Hearts, CardValue::King);
        $offSuit = $this->giveCard($round, $this->player($round, 0), Suit::Clubs, CardValue::Ace);

        $this->expectException(ValidationException::class);

        $this->trickService->playCard($trick->fresh(), $this->player($round, 0)->user, $offSuit);
    }

    public function test_a_void_player_may_play_off_suit(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $lead = $this->giveCard($round, $this->player($round, 1), Suit::Hearts, CardValue::Nine);
        $this->trickService->playCard($trick, $this->player($round, 1)->user, $lead);

        $offSuit = $this->giveCard($round, $this->player($round, 0), Suit::Clubs, CardValue::Ace);

        $trickCard = $this->trickService->playCard($trick->fresh(), $this->player($round, 0)->user, $offSuit);

        $this->assertSame(1, $trickCard->play_order);
    }

    public function test_highest_card_of_the_lead_suit_wins_when_no_trump_is_played(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $this->trickService->playCard($trick, $this->player($round, 1)->user, $this->giveCard($round, $this->player($round, 1), Suit::Hearts, CardValue::Nine));
        $this->trickService->playCard($trick->fresh(), $this->player($round, 0)->user, $this->giveCard($round, $this->player($round, 0), Suit::Hearts, CardValue::King));

        $this->assertSame($this->player($round, 0)->user_id, $trick->fresh()->winner_id);
    }

    public function test_trump_beats_a_higher_card_of_the_lead_suit(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $this->trickService->playCard($trick, $this->player($round, 1)->user, $this->giveCard($round, $this->player($round, 1), Suit::Hearts, CardValue::Ace));
        $this->trickService->playCard($trick->fresh(), $this->player($round, 0)->user, $this->giveCard($round, $this->player($round, 0), Suit::Spades, CardValue::Nine));

        $this->assertSame($this->player($round, 0)->user_id, $trick->fresh()->winner_id);
    }

    public function test_highest_trump_wins_when_several_are_played(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $this->trickService->playCard($trick, $this->player($round, 1)->user, $this->giveCard($round, $this->player($round, 1), Suit::Spades, CardValue::Nine));
        $this->trickService->playCard($trick->fresh(), $this->player($round, 0)->user, $this->giveCard($round, $this->player($round, 0), Suit::Spades, CardValue::King));

        $this->assertSame($this->player($round, 0)->user_id, $trick->fresh()->winner_id);
    }

    public function test_when_identical_cards_are_played_the_first_one_wins(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Diamonds);
        $trick = $this->trickService->currentTrick($round);

        $this->trickService->playCard($trick, $this->player($round, 1)->user, $this->giveCard($round, $this->player($round, 1), Suit::Hearts, CardValue::Ace, copy: 1));
        $this->trickService->playCard($trick->fresh(), $this->player($round, 0)->user, $this->giveCard($round, $this->player($round, 0), Suit::Hearts, CardValue::Ace, copy: 2));

        $this->assertSame($this->player($round, 1)->user_id, $trick->fresh()->winner_id);
    }

    public function test_completing_a_trick_opens_the_next_one_led_by_the_winner(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $this->trickService->playCard($trick, $this->player($round, 1)->user, $this->giveCard($round, $this->player($round, 1), Suit::Hearts, CardValue::Nine));
        $this->trickService->playCard($trick->fresh(), $this->player($round, 0)->user, $this->giveCard($round, $this->player($round, 0), Suit::Hearts, CardValue::King));

        $next = $this->trickService->currentTrick($round->fresh());

        $this->assertSame(2, $next->trick_number);
        $this->assertSame(RoundStatus::Playing, $round->fresh()->status);
        $this->assertSame(0, $this->trickService->currentPlayer($next)->seat_index);
    }

    public function test_completing_the_final_trick_completes_the_round(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 1, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $this->trickService->playCard($trick, $this->player($round, 1)->user, $this->giveCard($round, $this->player($round, 1), Suit::Hearts, CardValue::Nine));
        $this->trickService->playCard($trick->fresh(), $this->player($round, 0)->user, $this->giveCard($round, $this->player($round, 0), Suit::Hearts, CardValue::King));

        $this->assertSame(RoundStatus::Complete, $round->fresh()->status);
        $this->assertNull($this->trickService->currentTrick($round->fresh()));
    }

    public function test_completing_the_round_scores_the_bids(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 1, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $leader = $this->player($round, 1);
        $winner = $this->player($round, 0);

        $leaderBid = Bid::factory()->create(['round_id' => $round->id, 'user_id' => $leader->user_id, 'bid_amount' => 0]);
        $winnerBid = Bid::factory()->create(['round_id' => $round->id, 'user_id' => $winner->user_id, 'bid_amount' => 1]);

        $this->trickService->playCard($trick, $leader->user, $this->giveCard($round, $leader, Suit::Hearts, CardValue::Nine));
        $this->trickService->playCard($trick->fresh(), $winner->user, $this->giveCard($round, $winner, Suit::Hearts, CardValue::King));

        $this->assertSame(10, $leaderBid->fresh()->points_earned);
        $this->assertSame(11, $winnerBid->fresh()->points_earned);
        $this->assertSame(1, $winnerBid->fresh()->tricks_won);
        $this->assertSame(11, $winner->fresh()->total_score);
    }

    public function test_cannot_play_out_of_turn(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $card = $this->giveCard($round, $this->player($round, 0), Suit::Hearts, CardValue::Nine);

        $this->expectException(ValidationException::class);

        $this->trickService->playCard($trick, $this->player($round, 0)->user, $card);
    }

    public function test_cannot_play_a_card_that_is_not_in_your_hand(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);

        $othersCard = $this->giveCard($round, $this->player($round, 0), Suit::Hearts, CardValue::Nine);

        $this->expectException(ValidationException::class);

        $this->trickService->playCard($trick, $this->player($round, 1)->user, $othersCard);
    }

    public function test_cannot_play_into_a_round_that_is_not_in_play(): void
    {
        $round = $this->makePlayingRound(playerCount: 2, trickCount: 2, trump: Suit::Spades);
        $trick = $this->trickService->currentTrick($round);
        $round->update(['status' => RoundStatus::Bidding]);

        $card = $this->giveCard($round, $this->player($round, 1), Suit::Hearts, CardValue::Nine);

        $this->expectException(ValidationException::class);

        $this->trickService->playCard($trick->fresh(), $this->player($round, 1)->user, $card);
    }
}
