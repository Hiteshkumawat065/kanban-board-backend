<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds a per-board sequential `task_number` to cards. Numbering starts
     * at 101 for the first card on a board and increments by 1 thereafter,
     * so each board has its own independent counter (Board A's #101 is
     * unrelated to Board B's #101).
     *
     * Existing cards are backfilled in id-order per board so the oldest
     * card on a board becomes #101.
     */
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table): void {
            $table->unsignedInteger('task_number')->nullable()->after('board_id');
        });

        $boardIds = DB::table('cards')
            ->select('board_id')
            ->distinct()
            ->pluck('board_id');

        foreach ($boardIds as $boardId) {
            $cardIds = DB::table('cards')
                ->where('board_id', $boardId)
                ->orderBy('id')
                ->pluck('id');

            $next = 101;
            foreach ($cardIds as $cardId) {
                DB::table('cards')
                    ->where('id', $cardId)
                    ->update(['task_number' => $next]);
                $next++;
            }
        }

        Schema::table('cards', function (Blueprint $table): void {
            $table->unique(['board_id', 'task_number'], 'cards_board_id_task_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table): void {
            $table->dropUnique('cards_board_id_task_number_unique');
            $table->dropColumn('task_number');
        });
    }
};
