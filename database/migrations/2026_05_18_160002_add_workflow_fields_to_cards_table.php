<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table): void {
            // UAT lifecycle: pending (in UAT, awaiting mentor) | approved | rework (kicked back).
            // NULL = card has never been in UAT.
            $table->string('uat_status', 20)->nullable()->after('completed_at');

            // True when the mentor has requested rework. The card is moved back to
            // the todo-stage list and a red REWORK badge is rendered on it.
            $table->boolean('needs_rework')->default(false)->after('uat_status');

            // Priority badge — bumped to "high" automatically on rework.
            $table->string('priority', 10)->default('medium')->after('needs_rework');

            // Mentor who last approved / requested rework, for audit and email replies.
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('priority')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            $table->index('uat_status');
            $table->index('needs_rework');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['uat_status']);
            $table->dropIndex(['needs_rework']);
            $table->dropIndex(['priority']);
            $table->dropColumn([
                'uat_status',
                'needs_rework',
                'priority',
                'reviewed_by',
                'reviewed_at',
            ]);
        });
    }
};
