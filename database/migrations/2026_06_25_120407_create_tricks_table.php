<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tricks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->unsignedTinyInteger('trick_number');
            $table->enum('lead_suit', ['clubs', 'hearts', 'spades', 'diamonds'])->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['round_id', 'trick_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tricks');
    }
};
