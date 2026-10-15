<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class GameEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $tries = 5;

    public int $backoff = 3;

    public function __construct(public Game $game) {}

    public function broadcastOn(): Channel
    {
        return new PresenceChannel('game.'.$this->game->id);
    }

    public function broadcastAs(): string
    {
        return class_basename($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['game_id' => $this->game->id];
    }
}
