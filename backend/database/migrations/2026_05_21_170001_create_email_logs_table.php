<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email send history.
 *
 * Every dispatch through DynamicMailService writes one row here so the admin
 * UI can show:
 *   - which template was used,
 *   - the resolved subject / recipient,
 *   - delivery status (queued / sent / failed),
 *   - an optional error message when the SMTP transport blew up.
 *
 * The table is intentionally denormalised — we copy the template name &
 * subject snapshot so the log stays meaningful even if the template is
 * later renamed or soft-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('email_template_id')
                ->nullable()
                ->constrained('email_templates')
                ->nullOnDelete();
            $table->string('template_key', 120)->nullable()->index();
            $table->string('template_name', 180)->nullable();

            $table->string('to_email', 180)->index();
            $table->string('to_name', 180)->nullable();
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->string('from_email', 180)->nullable();
            $table->string('from_name', 180)->nullable();
            $table->string('reply_to', 180)->nullable();

            $table->string('subject', 255);
            $table->longText('body')->nullable();

            // 'queued' | 'sent' | 'failed'
            $table->string('status', 16)->default('queued')->index();
            $table->text('error')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->string('open_tracking_id', 64)->nullable()->unique();

            $table->json('context')->nullable();

            $table->foreignId('sent_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['email_template_id', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
