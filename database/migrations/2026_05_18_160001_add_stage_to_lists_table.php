<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lists', function (Blueprint $table): void {
            // Stable workflow stage identifier that survives renames.
            // Possible values map to App\Enums\ListStage:
            //   backlog | todo | in_progress | uat | done
            // NULL means the list is not part of the structured workflow.
            $table->string('stage', 20)->nullable()->after('color');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::table('lists', function (Blueprint $table): void {
            $table->dropIndex(['stage']);
            $table->dropColumn('stage');
        });
    }
};
