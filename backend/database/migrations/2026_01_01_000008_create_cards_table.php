<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')
                ->constrained('lists')
                ->cascadeOnDelete();
            $table->foreignId('board_id')
                ->constrained('boards')
                ->cascadeOnDelete();
            $table->string('title');
            $table->mediumText('description')->nullable();
            $table->decimal('position', 20, 10)->default(0);
            $table->string('cover_color', 20)->nullable();
            $table->string('cover_url', 500)->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['list_id', 'position']);
            $table->index(['board_id', 'archived_at']);
            $table->index('due_date');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
