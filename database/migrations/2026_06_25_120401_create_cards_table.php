<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->enum('suit', ['hearts', 'diamonds', 'clubs', 'spades']);
            $table->enum('value', ['9', '10', 'J', 'Q', 'K', 'A']);
            $table->unsignedTinyInteger('sort_order');
            $table->string('image_path');
            $table->string('label');
            $table->unsignedTinyInteger('copy');
            $table->timestamps();

            $table->unique(['suit', 'value', 'copy']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
