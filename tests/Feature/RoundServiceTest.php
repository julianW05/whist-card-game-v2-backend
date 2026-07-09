<?php

namespace Tests\Feature;

use App\Enums\DeckCardStatus;
use App\Enums\RoundStatus;
use App\Enums\Suit;
use App\Events\ScoreboardRevealed;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Round;
use App\Models\User;
use App\Services\RoundService;
use Database\Seeders\CardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RoundServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoundService $roundService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CardSeeder::class);
        $this->roundService = app(RoundService::class);
    }

    private function makeGame(int $playerCount): Game
    {
        $game = Game::factory()->create(['player_count' => $playerCount, 'dealer_index' => 0]);

        foreach (range(0, $playerCount - 1) as $seat) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'user_id' => User::factory()->create()->id,
                'seat_index' => $seat,
            ]);
        }

        return $game;
    }

    public function test_peak_trick_count_scales_with_player_count(): void
    {
        $this->assertSame(12, $this->roundService->peakTrickCount(2));
        $this->assertSame(12, $this->roundService->peakTrickCount(3));
        $this->assertSame(12, $this->roundService->peakTrickCount(4));
        $this->assertSame(9, $this->roundService->peakTrickCount(5));
        $this->assertSame(8, $this->roundService->peakTrickCount(6));
    }

    public function test_total_rounds_scales_with_player_count(): void
    {
        $this->assertSame(24, $this->roundService->totalRounds(4));
        $this->assertSame(18, $this->roundService->totalRounds(5));
        $this->assertSame(16, $this->roundService->totalRounds(6));
    }

    public function test_trick_count_follows_the_pyramid_then_final_at_peak(): void
    {
        $sequence = array_map(
            fn (int $round): int => $this->roundService->trickCountForRound($round, 4),
            range(1, 24),
        );

        $expected = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 12];

        $this->assertSame($expected, $sequence);
        $this->assertTrue($this->roundService->isFinalRound(24, 4));
        $this->assertFalse($this->roundService->isFinalRound(23, 4));
    }

    public function test_start_next_round_creates_a_dealt_normal_round(): void
    {
        $game = $this->makeGame(4);

        $round = $this->roundService->startNextRound($game);

        $this->assertSame(1, $round->round_number);
        $this->assertSame(1, $round->trick_count);
        $this->assertSame(Suit::Clubs, $round->trump_suit);
        $this->assertFalse($round->is_final);
        $this->assertSame(RoundStatus::Bidding, $round->status);

        $this->assertSame(48, $round->deck()->count());
        $this->assertSame(4, $round->deck()->where('status', DeckCardStatus::InHand)->count());

        $this->assertSame(1, $game->fresh()->current_round);
        $this->assertSame(0, $game->fresh()->dealer_index);
    }

    public function test_consecutive_rounds_rotate_trump_and_dealer(): void
    {
        $game = $this->makeGame(4);

        $this->roundService->startNextRound($game);
        $second = $this->roundService->startNextRound($game->fresh());

        $this->assertSame(2, $second->round_number);
        $this->assertSame(2, $second->trick_count);
        $this->assertSame(Suit::Hearts, $second->trump_suit);
        $this->assertSame(8, $second->deck()->where('status', DeckCardStatus::InHand)->count());
        $this->assertSame(1, $game->fresh()->dealer_index);
    }

    public function test_final_round_is_not_dealt(): void
    {
        $game = $this->makeGame(4);
        $game->update(['current_round' => 23]);

        $round = $this->roundService->startNextRound($game);

        $this->assertSame(24, $round->round_number);
        $this->assertTrue($round->is_final);
        $this->assertSame(12, $round->trick_count);
        $this->assertSame(48, $round->deck()->count());
        $this->assertSame(0, $round->deck()->where('status', DeckCardStatus::InHand)->count());
    }

    public function test_reveal_scoreboard_flags_the_round_and_broadcasts(): void
    {
        $game = $this->makeGame(2);
        $round = Round::factory()->create([
            'game_id' => $game->id,
            'status' => RoundStatus::Complete,
            'scoreboard_revealed' => false,
        ]);

        Event::fake([ScoreboardRevealed::class]);

        $this->roundService->revealScoreboard($round);

        $this->assertTrue($round->fresh()->scoreboard_revealed);
        Event::assertDispatched(ScoreboardRevealed::class);
    }
}
