<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')
                ->constrained('boards')
                ->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('color', 20)->nullable();
            $table->decimal('position', 20, 10)->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['board_id', 'position']);
            $table->index(['board_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lists');
    }
};
