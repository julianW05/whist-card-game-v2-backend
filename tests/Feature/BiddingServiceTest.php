<?php

namespace Tests\Feature;

use App\Enums\DeckCardStatus;
use App\Enums\RoundStatus;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\User;
use App\Services\BiddingService;
use App\Services\DeckService;
use Database\Seeders\CardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BiddingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BiddingService $biddingService;

    private DeckService $deckService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CardSeeder::class);
        $this->deckService = app(DeckService::class);
        $this->biddingService = app(BiddingService::class);
    }

    private function makeRound(int $playerCount, int $trickCount, int $dealerIndex = 0, bool $isFinal = false): Round
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

        return Round::factory()->create([
            'game_id' => $game->id,
            'trick_count' => $trickCount,
            'is_final' => $isFinal,
            'status' => RoundStatus::Bidding,
        ]);
    }

    private function player(Round $round, int $seat): GamePlayer
    {
        return $round->game->players()->where('seat_index', $seat)->first();
    }

    public function test_bidding_starts_left_of_dealer_and_dealer_bids_last(): void
    {
        $round = $this->makeRound(playerCount: 3, trickCount: 5, dealerIndex: 1);

        $order = $this->biddingService->biddingOrder($round->game);

        $this->assertSame([2, 0, 1], $order->pluck('seat_index')->all());
        $this->assertSame(2, $this->biddingService->currentBidder($round)->seat_index);
    }

    public function test_place_bid_records_the_bid_and_advances_the_turn(): void
    {
        $round = $this->makeRound(playerCount: 3, trickCount: 5, dealerIndex: 0);

        $bid = $this->biddingService->placeBid($round, $this->player($round, 1)->user, 2);

        $this->assertSame(2, $bid->bid_amount);
        $this->assertSame(1, $round->bids()->count());
        $this->assertSame(2, $this->biddingService->currentBidder($round)->seat_index);
    }

    public function test_cannot_bid_out_of_turn(): void
    {
        $round = $this->makeRound(playerCount: 3, trickCount: 5, dealerIndex: 0);

        $this->expectException(ValidationException::class);

        $this->biddingService->placeBid($round, $this->player($round, 2)->user, 1);
    }

    public function test_bid_must_be_within_range(): void
    {
        $round = $this->makeRound(playerCount: 3, trickCount: 5, dealerIndex: 0);

        $this->expectException(ValidationException::class);

        $this->biddingService->placeBid($round, $this->player($round, 1)->user, 6);
    }

    public function test_dealer_cannot_make_total_bids_equal_trick_count(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 2, dealerIndex: 0);

        $this->biddingService->placeBid($round, $this->player($round, 1)->user, 1);

        $this->assertSame(1, $this->biddingService->forbiddenBid($round));

        $this->expectException(ValidationException::class);

        $this->biddingService->placeBid($round, $this->player($round, 0)->user, 1);
    }

    public function test_dealer_may_bid_a_value_that_keeps_totals_uneven(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 2, dealerIndex: 0);

        $this->biddingService->placeBid($round, $this->player($round, 1)->user, 1);
        $this->biddingService->placeBid($round, $this->player($round, 0)->user, 0);

        $this->assertSame(2, $round->bids()->count());
        $this->assertSame(RoundStatus::Playing, $round->fresh()->status);
    }

    public function test_dealer_is_unconstrained_when_others_already_overshoot(): void
    {
        $round = $this->makeRound(playerCount: 3, trickCount: 2, dealerIndex: 0);

        $this->biddingService->placeBid($round, $this->player($round, 1)->user, 2);
        $this->biddingService->placeBid($round, $this->player($round, 2)->user, 1);

        $this->assertNull($this->biddingService->forbiddenBid($round));

        $bid = $this->biddingService->placeBid($round, $this->player($round, 0)->user, 2);

        $this->assertSame(2, $bid->bid_amount);
    }

    public function test_completing_bids_transitions_to_playing_and_opens_first_trick(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 3, dealerIndex: 0);

        $this->biddingService->placeBid($round, $this->player($round, 1)->user, 1);
        $this->assertSame(RoundStatus::Bidding, $round->fresh()->status);

        $this->biddingService->placeBid($round, $this->player($round, 0)->user, 1);

        $round->refresh();
        $this->assertSame(RoundStatus::Playing, $round->status);
        $this->assertSame(1, $round->tricks()->count());
        $this->assertSame(1, $round->tricks()->first()->trick_number);
        $this->assertNull($this->biddingService->currentBidder($round));
    }

    public function test_final_round_deals_hands_only_after_all_bids_are_placed(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 4, dealerIndex: 0, isFinal: true);
        $this->deckService->generateForRound($round);

        $this->biddingService->placeBid($round, $this->player($round, 1)->user, 2);

        $this->assertSame(0, $round->deck()->where('status', DeckCardStatus::InHand)->count());

        $this->biddingService->placeBid($round, $this->player($round, 0)->user, 1);

        $this->assertSame(8, $round->deck()->where('status', DeckCardStatus::InHand)->count());
        $this->assertSame(RoundStatus::Playing, $round->fresh()->status);
    }

    public function test_cannot_bid_once_round_has_left_bidding(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 3, dealerIndex: 0);
        $round->update(['status' => RoundStatus::Playing]);

        $this->expectException(ValidationException::class);

        $this->biddingService->placeBid($round, $this->player($round, 1)->user, 1);
    }
}
