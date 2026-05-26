<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Self-referential mentor link: a developer optionally has one mentor (another user).
            // Nullable because not every user is necessarily mentored.
            $table->foreignId('mentor_id')
                ->nullable()
                ->after('timezone')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('mentor_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['mentor_id']);
            $table->dropIndex(['mentor_id']);
            $table->dropColumn('mentor_id');
        });
    }
};
