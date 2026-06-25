<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique();
            $table->string('name');
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['lobby', 'active', 'finished'])->default('lobby');
            $table->boolean('is_public')->default(false);
            $table->unsignedTinyInteger('player_count')->default(0);
            $table->unsignedSmallInteger('current_round')->default(0);
            $table->unsignedTinyInteger('dealer_index')->default(0);
            $table->timestamps();

            $table->index(['is_public', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
