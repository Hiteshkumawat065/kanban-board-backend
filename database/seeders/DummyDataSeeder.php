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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds 10 realistic workspaces for the demo user, each with 10 boards
 * containing a full Kanban workflow (Backlog → Done) and several tasks
 * per list. Run with:
 *
 *   php artisan db:seed --class=DummyDataSeeder
 */
final class DummyDataSeeder extends Seeder
{
    /**
     * Realistic workspace names so the listing screens look like a
     * real product instead of "Workspace 1 / Workspace 2 / …".
     *
     * @var list<array{name: string, description: string}>
     */
    private const WORKSPACES = [
        ['name' => 'Engineering Hub',   'description' => 'Primary workspace for product engineering work.'],
        ['name' => 'Marketing Team',    'description' => 'Campaigns, content calendar and growth experiments.'],
        ['name' => 'Design Studio',     'description' => 'Product design, branding and visual assets.'],
        ['name' => 'Sales Operations',  'description' => 'Pipeline reviews, deal tracking and forecasting.'],
        ['name' => 'Customer Success',  'description' => 'Onboarding playbooks and renewal motions.'],
        ['name' => 'People Ops',        'description' => 'Hiring, onboarding and employee programs.'],
        ['name' => 'Finance',           'description' => 'Budgeting, vendor invoices and quarterly close.'],
        ['name' => 'Product Research',  'description' => 'User interviews, surveys and competitive analysis.'],
        ['name' => 'DevOps & Infra',    'description' => 'Cloud infra, CI/CD and on-call rotations.'],
        ['name' => 'Mobile Squad',      'description' => 'iOS and Android release planning.'],
    ];

    /**
     * Realistic board names per workspace. We pick 10 from this pool so
     * each workspace has 10 boards with believable, varied titles.
     *
     * @var list<string>
     */
    private const BOARD_NAMES = [
        'Website Redesign 2026',
        'Q1 Roadmap',
        'Q2 Roadmap',
        'Mobile App v3',
        'Sprint Backlog',
        'Bug Bash',
        'Onboarding Revamp',
        'Performance Improvements',
        'Customer Feedback',
        'Internal Tools',
    ];

    /**
     * Sample card titles grouped by list stage. The seeder picks a few
     * titles from the matching bucket so cards "fit" the list they sit in.
     *
     * @var array<string, list<string>>
     */
    private const CARD_TITLES = [
        'Backlog' => [
            'Define project scope and milestones',
            'Research competitor analytics dashboards',
            'Draft new pricing page copy',
            'Audit accessibility on signup flow',
            'Plan migration off legacy queue',
            'Collect feature requests from CS',
        ],
        'To Do' => [
            'Build login page',
            'Wire up password reset email',
            'Add 2FA setup screen',
            'Refactor billing service',
            'Set up Sentry alert rules',
            'Write release notes template',
        ],
        'In Progress' => [
            'Design new homepage hero section',
            'Implement file upload to S3',
            'Migrate auth to Sanctum',
            'Optimize dashboard SQL queries',
            'Build admin user impersonation',
        ],
        'UAT' => [
            'Review checkout page redesign',
            'QA the new permissions matrix',
            'Validate analytics events',
            'Stakeholder review: pricing page',
        ],
        'Ready To Test' => [
            'Implement user authentication flow',
            'Set up CI/CD pipeline',
            'QA pass on contact form',
            'Smoke test signup flow',
        ],
        'Completed' => [
            'Set up project repository',
            'Provision staging environment',
            'Publish v1 design tokens',
            'Migrate logs to Datadog',
        ],
        'Hold Tasks' => [
            'Migrate legacy reports — blocked on data team',
            'Vendor onboarding — waiting on legal',
        ],
        'Closed Task' => [
            'Decommission old marketing site',
            'Sunset experimental feature flag',
        ],
        'Other Products' => [
            'Cross-team sync notes',
            'Shared component library updates',
        ],
    ];

    /**
     * Board label palette — same colors the demo seeder uses so the UI
     * stays visually consistent.
     *
     * @var list<array{name: string, color: string}>
     */
    private const LABELS = [
        ['name' => 'Bug',     'color' => '#eb5a46'],
        ['name' => 'Feature', 'color' => '#61bd4f'],
        ['name' => 'Urgent',  'color' => '#f2d600'],
        ['name' => 'Design',  'color' => '#c377e0'],
    ];

    public function run(): void
    {
        // Run inside a transaction so a partial failure doesn't leave the
        // database with half-populated workspaces.
        DB::transaction(function (): void {
            $owner = $this->resolveOwner();
            $members = $this->resolveMemberPool();

            foreach (self::WORKSPACES as $wsSpec) {
                $workspace = $this->createWorkspace($wsSpec, $owner, $members);
                $this->seedBoardsFor($workspace, $owner, $members);
            }
        });

        $this->command->info('DummyDataSeeder: created 10 workspaces × 10 boards with tasks for demo12@yopmail.com');
    }

    /**
     * Find the demo user or create a fresh one. We never modify password /
     * email_verified_at on an existing row so existing logins keep working.
     */
    private function resolveOwner(): User
    {
        return User::firstOrCreate(
            ['email' => 'demo12@yopmail.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * A small pool of additional users to attach as workspace members and
     * card assignees so the seeded data has multiple humans involved.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function resolveMemberPool(): \Illuminate\Support\Collection
    {
        // Reuse any developers already present (created by DatabaseSeeder),
        // and top up to at least 4 random users for variety.
        $existing = User::where('email', '!=', 'demo12@yopmail.com')
            ->orderBy('id')
            ->limit(6)
            ->get();

        if ($existing->count() < 4) {
            $needed = 4 - $existing->count();
            $extras = User::factory()->count($needed)->create();
            $existing = $existing->concat($extras);
        }

        return $existing;
    }

    /**
     * Create a workspace + attach owner / member pivot rows.
     *
     * @param  array{name: string, description: string}  $spec
     * @param  \Illuminate\Support\Collection<int, User>  $members
     */
    private function createWorkspace(array $spec, User $owner, \Illuminate\Support\Collection $members): Workspace
    {
        $workspace = Workspace::create([
            'name' => $spec['name'],
            'description' => $spec['description'],
            'owner_id' => $owner->id,
        ]);

        $workspace->members()->attach($owner->id, [
            'role' => WorkspaceRole::Owner->value,
            'joined_at' => now(),
        ]);

        foreach ($members as $m) {
            $workspace->members()->attach($m->id, [
                'role' => WorkspaceRole::Member->value,
                'joined_at' => now(),
            ]);
        }

        return $workspace;
    }

    /**
     * Create 10 boards for a workspace, each with the standard Kanban
     * workflow (Backlog / To Do / In Progress / UAT / Ready To Test /
     * Completed / Hold / Closed / Other) and a handful of cards per list.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $members
     */
    private function seedBoardsFor(Workspace $workspace, User $owner, \Illuminate\Support\Collection $members): void
    {
        $colors = ['#0079bf', '#d29034', '#519839', '#b04632', '#89609e', '#cd5a91', '#00aecc', '#838c91'];

        foreach (self::BOARD_NAMES as $i => $boardName) {
            $board = Board::create([
                'workspace_id' => $workspace->id,
                'name' => $boardName,
                'description' => 'Track all '.strtolower($workspace->name).' work for the '.$boardName.' initiative.',
                'background_color' => $colors[$i % count($colors)],
                'visibility' => BoardVisibility::Workspace,
                'created_by' => $owner->id,
            ]);

            foreach (self::LABELS as $l) {
                Label::create([...$l, 'board_id' => $board->id]);
            }

            $this->seedListsFor($board, $owner, $members);
        }
    }

    /**
     * Create the standard list set for a board and populate each with a
     * few cards drawn from the matching CARD_TITLES bucket.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $members
     */
    private function seedListsFor(Board $board, User $owner, \Illuminate\Support\Collection $members): void
    {
        $listSpec = [
            ['name' => 'Backlog',        'stage' => ListStage::Backlog],
            ['name' => 'To Do',          'stage' => ListStage::Todo],
            ['name' => 'In Progress',    'stage' => ListStage::InProgress],
            ['name' => 'UAT',            'stage' => ListStage::Uat],
            ['name' => 'Ready To Test',  'stage' => null],
            ['name' => 'Completed',      'stage' => ListStage::Done],
            ['name' => 'Hold Tasks',     'stage' => null],
            ['name' => 'Closed Task',    'stage' => null],
            ['name' => 'Other Products', 'stage' => null],
        ];

        $position = 1.0;
        foreach ($listSpec as $spec) {
            $list = BoardList::create([
                'board_id' => $board->id,
                'name' => $spec['name'],
                'stage' => $spec['stage']?->value,
                'position' => $position,
            ]);
            $position += 1.0;

            $this->seedCardsFor($list, $board, $owner, $members);
        }
    }

    /**
     * Pick 2–4 sample card titles for the list and create matching cards.
     * One card per list also gets a random assignee from the member pool
     * so the assignee chips render on the board.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $members
     */
    private function seedCardsFor(BoardList $list, Board $board, User $owner, \Illuminate\Support\Collection $members): void
    {
        $titles = self::CARD_TITLES[$list->name] ?? ['Sample task'];
        // 2 - min(4, available titles) cards per list — keeps the board
        // populated without becoming overwhelming.
        $count = min(count($titles), random_int(2, 4));
        $picks = collect($titles)->shuffle()->take($count);

        $cardPos = 1.0;
        $priorities = ['low', 'medium', 'high'];

        foreach ($picks as $idx => $title) {
            $card = Card::factory()->create([
                'list_id' => $list->id,
                'board_id' => $board->id,
                'created_by' => $owner->id,
                'title' => $title,
                'position' => $cardPos,
                'priority' => $priorities[array_rand($priorities)],
            ]);

            // Assign the first card of each list to a random member so the
            // board has assignee chips to render.
            if ($idx === 0 && $members->isNotEmpty()) {
                $assignee = $members->random();
                $card->assignees()->attach($assignee->id, [
                    'assigned_by' => $owner->id,
                    'assigned_at' => now(),
                ]);
            }

            $cardPos += 1.0;
        }
    }
}
