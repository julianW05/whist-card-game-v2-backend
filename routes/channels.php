<?php

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('game.{game}', function (User $user, Game $game): ?array {
    if (! $game->players()->where('user_id', $user->id)->exists()) {
        return null;
    }

    return ['id' => $user->id, 'name' => $user->name];
}, ['guards' => ['sanctum']]);
