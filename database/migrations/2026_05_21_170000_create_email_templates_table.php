<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email Templates — central registry of reusable transactional/marketing
 * emails. Every email the app sends through {@see App\Services\EmailTemplate\DynamicMailService}
 * is resolved by `template_key` so application code never hard-codes copy.
 *
 *   - `template_key` is the stable identifier (e.g. `welcome.user`) used by
 *     the app to fetch the template. It is unique and never reused.
 *   - `slug` is the human-readable URL fragment for the admin UI.
 *   - `body_content` stores the rich HTML body authored in the admin editor.
 *     Variables are written as `{{ user_name }}` and resolved at send-time
 *     by the VariableParser service.
 *   - `is_system_template` flags rows shipped by the seeder. The UI hides
 *     destructive actions on these so they can't be accidentally removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();

            $table->string('template_name', 180);
            $table->string('template_key', 120)->unique();
            $table->string('slug', 180)->unique();
            $table->string('subject', 255);
            $table->string('category', 32)->default('other')->index();
            $table->text('description')->nullable();

            // Sender / reply configuration overrides. When null, the global
            // `config('mail.from')` defaults apply.
            $table->string('email_from_name', 180)->nullable();
            $table->string('email_from_email', 180)->nullable();
            $table->string('reply_to', 180)->nullable();
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();

            // List of variable definitions exposed to the template author.
            // Each entry is `{ key, label, sample }`. Used by the UI's variable
            // palette and by the VariableParser to fall back to a sample when
            // sending a test email without runtime context.
            $table->json('variables')->nullable();

            $table->longText('body_content');
            $table->longText('plain_text')->nullable();
            $table->json('attachments')->nullable();

            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_system_template')->default(false)->index();

            $table->string('locale', 12)->default('en');
            $table->unsignedInteger('version')->default(1);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
