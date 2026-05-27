<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration that brings already-seeded boards in line with the
 * new default list layout:
 *
 *   - "Ready To Test"  ->  "QA Testing"
 *   - "Closed Task"    ->  "Closed Tasks"
 *   - Insert a brand-new "Released On Live" list immediately before
 *     the "Completed" (stage = done) list on every existing board.
 *
 * Idempotent: re-running won't duplicate the "Released On Live" list
 * on boards that already have one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // 1. Rename existing lists in-place. Targets stage=null custom
            //    lists, so we match by name rather than by stage.
            DB::table('lists')
                ->where('name', 'Ready To Test')
                ->update(['name' => 'QA Testing']);

            DB::table('lists')
                ->where('name', 'Closed Task')
                ->update(['name' => 'Closed Tasks']);

            // 2. Add a "Released On Live" list to every board that has a
            //    "Completed" (stage = done) list but doesn't already have
            //    a "Released On Live" list. Insert it just before the
            //    Completed list using a midpoint position so we don't
            //    have to renumber every other list on the board.
            $completedLists = DB::table('lists')
                ->where('stage', 'done')
                ->get(['id', 'board_id', 'position']);

            foreach ($completedLists as $completed) {
                $exists = DB::table('lists')
                    ->where('board_id', $completed->board_id)
                    ->where('name', 'Released On Live')
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Position of the list immediately before "Completed" on
                // this board (if any) so we can pick a midpoint.
                $prevPosition = DB::table('lists')
                    ->where('board_id', $completed->board_id)
                    ->where('position', '<', $completed->position)
                    ->max('position');

                $newPosition = $prevPosition !== null
                    ? ((float) $prevPosition + (float) $completed->position) / 2
                    : (float) $completed->position / 2;

                DB::table('lists')->insert([
                    'board_id' => $completed->board_id,
                    'name' => 'Released On Live',
                    'stage' => null,
                    'position' => $newPosition,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Revert the renames.
            DB::table('lists')
                ->where('name', 'QA Testing')
                ->update(['name' => 'Ready To Test']);

            DB::table('lists')
                ->where('name', 'Closed Tasks')
                ->update(['name' => 'Closed Task']);

            // Remove every "Released On Live" list that has no cards on
            // it. We intentionally keep lists that already received user
            // data so a rollback never destroys real work.
            $releasedLists = DB::table('lists')
                ->where('name', 'Released On Live')
                ->get(['id']);

            foreach ($releasedLists as $list) {
                $hasCards = DB::table('cards')
                    ->where('list_id', $list->id)
                    ->exists();

                if (! $hasCards) {
                    DB::table('lists')->where('id', $list->id)->delete();
                }
            }
        });
    }
};
