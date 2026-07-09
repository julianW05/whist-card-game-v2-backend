<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\RoundController;
use App\Http\Controllers\TrickController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::get('/games', [GameController::class, 'index']);
    Route::get('/games/mine', [GameController::class, 'mine']);
    Route::post('/games', [GameController::class, 'store']);
    Route::post('/games/{game:code}/join', [GameController::class, 'join']);
    Route::post('/games/{game}/start', [GameController::class, 'start']);
    Route::post('/games/{game}/kick/{user}', [GameController::class, 'kick']);
    Route::post('/games/{game}/leave', [GameController::class, 'leave']);
    Route::get('/games/{game}/state', [GameController::class, 'state']);
    Route::post('/games/{game}/sync', [GameController::class, 'sync']);
    Route::get('/games/{game}/hand', [GameController::class, 'hand']);

    Route::post('/rounds/{round}/bid', [RoundController::class, 'bid']);
    Route::post('/rounds/{round}/reveal-scoreboard', [RoundController::class, 'revealScoreboard']);
    Route::post('/rounds/{round}/start-next', [RoundController::class, 'startNext']);

    Route::post('/tricks/{trick}/play', [TrickController::class, 'play']);
});

require __DIR__.'/auth.php';
