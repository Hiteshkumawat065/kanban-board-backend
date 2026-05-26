<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rewrites every user's email so the local-part is preserved but the
 * domain is forced to `@yopmail.com`. Yopmail is a disposable inbox
 * service we use during local / staging testing so we can read every
 * notification sent by the app without configuring real SMTP boxes.
 *
 * Usage:
 *   php artisan users:yopmailize           # apply the change
 *   php artisan users:yopmailize --dry     # preview without writing
 *
 * The command is idempotent — addresses already on @yopmail.com are
 * skipped, and conflicts on the new email (very unlikely, but possible
 * if two users had identical local-parts on different domains) are
 * resolved by appending the user id to keep the unique constraint
 * happy.
 */
final class YopmailizeUsersCommand extends Command
{
    protected $signature = 'users:yopmailize {--dry : Show the changes without writing them}';

    protected $description = 'Force every user\'s email to use the @yopmail.com domain';

    private const TARGET_DOMAIN = 'yopmail.com';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry');

        $users = User::orderBy('id')->get();
        if ($users->isEmpty()) {
            $this->info('No users found.');

            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $taken = [];

        // Pre-seed the taken-set with addresses we are NOT touching so the
        // collision check works against the final state of the table.
        foreach ($users as $u) {
            if ($this->domainOf($u->email) === self::TARGET_DOMAIN) {
                $taken[strtolower($u->email)] = true;
            }
        }

        DB::transaction(function () use ($users, &$updated, &$skipped, &$taken, $dryRun): void {
            foreach ($users as $user) {
                if ($this->domainOf($user->email) === self::TARGET_DOMAIN) {
                    $skipped++;
                    continue;
                }

                $localPart = $this->sanitizeLocalPart(
                    explode('@', $user->email)[0] ?? Str::slug($user->name)
                );
                $candidate = $localPart.'@'.self::TARGET_DOMAIN;

                // Collision -> append the user id (guaranteed unique).
                if (isset($taken[strtolower($candidate)])) {
                    $candidate = $localPart.$user->id.'@'.self::TARGET_DOMAIN;
                }
                $taken[strtolower($candidate)] = true;

                $this->line(sprintf('  #%-4d %s  ->  %s', $user->id, $user->email, $candidate));

                if (! $dryRun) {
                    $user->forceFill(['email' => $candidate])->save();
                }
                $updated++;
            }
        });

        $this->newLine();
        if ($dryRun) {
            $this->warn("Dry run — no changes written. Would update {$updated} user(s), skip {$skipped}.");
        } else {
            $this->info("Updated {$updated} user email(s) to @yopmail.com. Skipped {$skipped} already-yopmail user(s).");
        }

        return self::SUCCESS;
    }

    private function domainOf(string $email): string
    {
        $parts = explode('@', $email, 2);

        return strtolower($parts[1] ?? '');
    }

    /**
     * Yopmail accepts most characters but we keep the local-part to a
     * safe ASCII/dot/dash/underscore subset so the address is always
     * RFC 5321 friendly and easy to read.
     */
    private function sanitizeLocalPart(string $raw): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._+-]/', '', $raw) ?? '';
        $clean = trim($clean, '.');

        return $clean === '' ? 'user' : strtolower($clean);
    }
}
