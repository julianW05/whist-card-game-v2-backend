<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->unsignedTinyInteger('trick_count');
            $table->enum('trump_suit', ['clubs', 'hearts', 'spades', 'diamonds']);
            $table->boolean('is_final')->default(false);
            $table->enum('status', ['bidding', 'playing', 'complete'])->default('bidding');
            $table->timestamps();

            $table->unique(['game_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
