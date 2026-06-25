<?php

namespace App\Services;

use App\Enums\DeckCardStatus;
use App\Models\Card;
use App\Models\GameDeck;
use App\Models\Round;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DeckService
{
    public function generateForRound(Round $round): void
    {
        DB::transaction(function () use ($round) {
            $positions = range(0, 47);
            shuffle($positions);

            $now = now();
            $rows = Card::query()->get()->values()->map(fn (Card $card, int $index): array => [
                'game_id' => $round->game_id,
                'round_id' => $round->id,
                'card_id' => $card->id,
                'position' => $positions[$index],
                'status' => DeckCardStatus::InDeck->value,
                'held_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            GameDeck::query()->insert($rows);
        });
    }

    public function deal(Round $round): void
    {
        DB::transaction(function () use ($round) {
            $players = $round->game->players()->orderBy('seat_index')->get();
            $perHand = $round->trick_count;

            $cards = $round->deck()
                ->where('status', DeckCardStatus::InDeck)
                ->orderBy('position')
                ->limit($players->count() * $perHand)
                ->get();

            foreach ($players as $index => $player) {
                $hand = $cards->slice($index * $perHand, $perHand);

                GameDeck::query()
                    ->whereIn('id', $hand->pluck('id'))
                    ->update([
                        'status' => DeckCardStatus::InHand,
                        'held_by' => $player->user_id,
                    ]);
            }
        });
    }

    /**
     * @return Collection<int, GameDeck>
     */
    public function getHand(Round $round, User $user): Collection
    {
        return $round->deck()
            ->where('held_by', $user->id)
            ->where('status', DeckCardStatus::InHand)
            ->with('card')
            ->orderBy('position')
            ->get();
    }

    public function playCard(GameDeck $gameDeckEntry): void
    {
        $gameDeckEntry->update([
            'status' => DeckCardStatus::Discarded,
            'held_by' => null,
        ]);
    }
}
