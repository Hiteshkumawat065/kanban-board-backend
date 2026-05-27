<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BoardVisibility;
use App\Enums\ListStage;
use App\Enums\WorkspaceRole;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------------------------------------------
        // Users: 1 demo (owner+mentor) + 1 explicit mentor + 4 developers
        // The developers all have $mentor as their mentor. Demo also acts
        // as a mentor for the demo flow.
        // -----------------------------------------------------------------
        $demo = User::firstOrCreate(
            ['email' => 'demo12@yopmail.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $mentor = User::firstOrCreate(
            ['email' => 'mentor12@yopmail.com'],
            [
                'name' => 'Maya Mentor',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $developers = User::factory()->count(4)->create()->each(function (User $user) use ($mentor): void {
            $user->update(['mentor_id' => $mentor->id]);
        });

        // -----------------------------------------------------------------
        // Workspace
        // -----------------------------------------------------------------
        $workspace = Workspace::create([
            'name' => 'Acme Corp',
            'description' => 'Demo workspace seeded automatically.',
            'owner_id' => $demo->id,
        ]);

        $workspace->members()->attach($demo->id, [
            'role' => WorkspaceRole::Owner->value,
            'joined_at' => now(),
        ]);

        $workspace->members()->attach($mentor->id, [
            'role' => WorkspaceRole::Admin->value,
            'joined_at' => now(),
        ]);

        foreach ($developers as $dev) {
            $workspace->members()->attach($dev->id, [
                'role' => WorkspaceRole::Member->value,
                'joined_at' => now(),
            ]);
        }

        // -----------------------------------------------------------------
        // Board + labels
        // -----------------------------------------------------------------
        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Sprint Board',
            'description' => 'Current sprint tasks',
            'background_color' => '#0079bf',
            'visibility' => BoardVisibility::Workspace,
            'created_by' => $demo->id,
        ]);

        foreach ([
            ['name' => 'Bug', 'color' => '#eb5a46'],
            ['name' => 'Feature', 'color' => '#61bd4f'],
            ['name' => 'Urgent', 'color' => '#f2d600'],
            ['name' => 'Design', 'color' => '#c377e0'],
        ] as $l) {
            Label::create([...$l, 'board_id' => $board->id]);
        }

        // -----------------------------------------------------------------
        // Lists — each list with a stable workflow `stage`.
        // The workflow logic in CardService keys off `stage`, not `name`,
        // so lists can be renamed freely.
        // -----------------------------------------------------------------
        $listSpec = [
            ['name' => 'Backlog', 'stage' => ListStage::Backlog],
            ['name' => 'To Do', 'stage' => ListStage::Todo],
            ['name' => 'In Progress', 'stage' => ListStage::InProgress],
            ['name' => 'UAT', 'stage' => ListStage::Uat],
            ['name' => 'QA Testing', 'stage' => null],
            ['name' => 'Released On Live', 'stage' => null],
            ['name' => 'Completed', 'stage' => ListStage::Done],
            ['name' => 'Hold Tasks', 'stage' => null],
            ['name' => 'Closed Tasks', 'stage' => null],
            ['name' => 'Other Products', 'stage' => null],
        ];

        $pos = 1.0;
        $lists = [];

        foreach ($listSpec as $spec) {
            $list = BoardList::create([
                'board_id' => $board->id,
                'name' => $spec['name'],
                'stage' => $spec['stage']?->value,
                'position' => $pos,
            ]);
            $lists[$spec['name']] = $list;
            $pos += 1.0;

            $cardPos = 1.0;
            foreach (range(1, 2) as $_) {
                Card::factory()->create([
                    'list_id' => $list->id,
                    'board_id' => $board->id,
                    'created_by' => $demo->id,
                    'position' => $cardPos,
                ]);
                $cardPos += 1.0;
            }
        }

        // -----------------------------------------------------------------
        // A clearly-assigned demo card sitting in Backlog so the workflow
        // is easy to exercise: drag it Backlog -> To Do and you should
        // see an email logged for the mentor.
        // -----------------------------------------------------------------
        $featureCard = Card::factory()->create([
            'list_id' => $lists['Backlog']->id,
            'board_id' => $board->id,
            'created_by' => $mentor->id,
            'title' => 'Build login page',
            'description' => 'Demo task assigned to a developer — drag through the workflow to trigger mentor emails.',
            'position' => 100.0,
            'priority' => 'medium',
        ]);

        $primaryDev = $developers->first();
        $featureCard->assignees()->attach($primaryDev->id, [
            'assigned_by' => $mentor->id,
            'assigned_at' => now(),
        ]);

        // -----------------------------------------------------------------
        // Email template registry — default transactional templates that
        // power DynamicMailService throughout the app.
        // -----------------------------------------------------------------
        $this->call(EmailTemplateSeeder::class);

        $this->command->info('Seeded users:');
        $this->command->info('  demo12@yopmail.com     / password   (owner)');
        $this->command->info('  mentor12@yopmail.com   / password   (mentor of all 4 developers)');
        foreach ($developers as $dev) {
            $this->command->info("  {$dev->email} / password   (developer)");
        }
        $this->command->info('"Build login page" is assigned to ' . $primaryDev->email . ' — drag it to trigger mails.');
    }
}
