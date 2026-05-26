<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Functional role used to group workspace members on the
            // board listing screen (Developers / Designers / QA / Manager).
            // Distinct from workspace_members.role which is permissions.
            $table->enum('job_title', ['developer', 'designer', 'qa', 'manager', 'other'])
                ->default('other')
                ->after('mentor_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('job_title');
        });
    }
};
