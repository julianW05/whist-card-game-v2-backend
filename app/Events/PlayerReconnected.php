<?php

namespace App\Events;

use App\Models\Game;
use App\Models\User;

class PlayerReconnected extends GameEvent
{
    public function __construct(Game $game, public User $user)
    {
        parent::__construct($game);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge(parent::broadcastWith(), [
            'reconnected_user_id' => $this->user->id,
        ]);
    }
}
