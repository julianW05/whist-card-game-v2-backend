<?php

namespace App\Events;

use App\Http\Resources\GameStateResource;
use App\Models\Game;
use App\Services\GameStateService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class GameEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

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
        $state = app(GameStateService::class)->build($this->game->fresh(), null);

        return json_decode(json_encode(new GameStateResource($state)), true);
    }
}
