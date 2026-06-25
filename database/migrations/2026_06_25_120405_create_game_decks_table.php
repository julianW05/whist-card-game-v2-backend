<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('cards')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->enum('status', ['in_deck', 'in_hand', 'discarded'])->default('in_deck');
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['round_id', 'position']);
            $table->index(['round_id', 'held_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_decks');
    }
};
