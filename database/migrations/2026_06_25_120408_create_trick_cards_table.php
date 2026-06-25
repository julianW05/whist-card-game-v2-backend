<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trick_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trick_id')->constrained('tricks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_deck_id')->constrained('game_decks')->cascadeOnDelete();
            $table->unsignedTinyInteger('play_order');
            $table->timestamps();

            $table->unique(['trick_id', 'play_order']);
            $table->unique(['trick_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trick_cards');
    }
};
