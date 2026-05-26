<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')
                ->constrained('boards')
                ->cascadeOnDelete();
            $table->string('name', 60)->nullable();
            $table->string('color', 20);
            $table->timestamps();

            $table->index('board_id');
        });

        Schema::create('card_label', function (Blueprint $table) {
            $table->foreignId('card_id')
                ->constrained('cards')
                ->cascadeOnDelete();
            $table->foreignId('label_id')
                ->constrained('labels')
                ->cascadeOnDelete();

            $table->primary(['card_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_label');
        Schema::dropIfExists('labels');
    }
};
