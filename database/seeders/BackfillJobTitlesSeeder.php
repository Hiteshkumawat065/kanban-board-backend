<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\JobTitle;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Distributes a realistic job_title across every user so the board listing
 * screen's team-counts row has meaningful data. Idempotent — running it
 * again just re-applies the same distribution.
 *
 *   php artisan db:seed --class=BackfillJobTitlesSeeder
 */
final class BackfillJobTitlesSeeder extends Seeder
{
    /**
     * Distribution used to bucket users by job_title. The pattern is
     * weighted toward developers (most common on a real team).
     *
     * @var list<JobTitle>
     */
    private const DISTRIBUTION = [
        JobTitle::Developer,
        JobTitle::Developer,
        JobTitle::Developer,
        JobTitle::Designer,
        JobTitle::Designer,
        JobTitle::Qa,
        JobTitle::Qa,
        JobTitle::Manager,
    ];

    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $count = count(self::DISTRIBUTION);

        foreach ($users as $i => $user) {
            $user->update([
                'job_title' => self::DISTRIBUTION[$i % $count]->value,
            ]);
        }

        foreach (JobTitle::cases() as $jt) {
            $n = User::where('job_title', $jt->value)->count();
            $this->command->info(sprintf('  %-12s %d', $jt->label().':', $n));
        }
    }
}
