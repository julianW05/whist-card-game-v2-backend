<?php

namespace Tests\Feature;

use App\Enums\RoundStatus;
use App\Models\Bid;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\Trick;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScoringService $scoringService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scoringService = app(ScoringService::class);
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
            'status' => RoundStatus::Complete,
        ]);
    }

    private function player(Round $round, int $seat): GamePlayer
    {
        return $round->game->players()->where('seat_index', $seat)->first();
    }

    private function bid(Round $round, GamePlayer $player, int $amount): Bid
    {
        return Bid::factory()->create([
            'round_id' => $round->id,
            'user_id' => $player->user_id,
            'bid_amount' => $amount,
        ]);
    }

    private function trickWonBy(Round $round, int $trickNumber, GamePlayer $player): void
    {
        Trick::factory()->create([
            'round_id' => $round->id,
            'trick_number' => $trickNumber,
            'winner_id' => $player->user_id,
        ]);
    }

    public function test_a_correct_prediction_earns_ten_plus_the_bid(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 3);
        $bid = $this->bid($round, $this->player($round, 0), 2);
        $this->bid($round, $this->player($round, 1), 1);

        $this->trickWonBy($round, 1, $this->player($round, 0));
        $this->trickWonBy($round, 2, $this->player($round, 0));
        $this->trickWonBy($round, 3, $this->player($round, 1));

        $this->scoringService->scoreRound($round);

        $bid->refresh();
        $this->assertSame(2, $bid->tricks_won);
        $this->assertSame(12, $bid->points_earned);
        $this->assertSame(12, $this->player($round, 0)->fresh()->total_score);
    }

    public function test_an_incorrect_prediction_loses_a_single_point(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 3);
        $bid = $this->bid($round, $this->player($round, 0), 2);
        $this->bid($round, $this->player($round, 1), 1);

        $this->trickWonBy($round, 1, $this->player($round, 0));
        $this->trickWonBy($round, 2, $this->player($round, 1));
        $this->trickWonBy($round, 3, $this->player($round, 1));

        $this->scoringService->scoreRound($round);

        $bid->refresh();
        $this->assertSame(1, $bid->tricks_won);
        $this->assertSame(-1, $bid->points_earned);
        $this->assertSame(-1, $this->player($round, 0)->fresh()->total_score);
    }

    public function test_a_correct_zero_bid_scores_ten(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 2);
        $bid = $this->bid($round, $this->player($round, 0), 0);
        $this->bid($round, $this->player($round, 1), 2);

        $this->trickWonBy($round, 1, $this->player($round, 1));
        $this->trickWonBy($round, 2, $this->player($round, 1));

        $this->scoringService->scoreRound($round);

        $bid->refresh();
        $this->assertSame(0, $bid->tricks_won);
        $this->assertSame(10, $bid->points_earned);
    }

    public function test_points_accumulate_onto_the_existing_total_score(): void
    {
        $round = $this->makeRound(playerCount: 2, trickCount: 2);
        $player = $this->player($round, 0);
        $player->update(['total_score' => 25]);

        $this->bid($round, $player, 1);
        $this->bid($round, $this->player($round, 1), 1);

        $this->trickWonBy($round, 1, $player);
        $this->trickWonBy($round, 2, $this->player($round, 1));

        $this->scoringService->scoreRound($round);

        $this->assertSame(36, $player->fresh()->total_score);
    }
}
