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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all workspace / board / card data (so primary keys restart from 1)
 * and seeds a small, clean demo dataset:
 *
 *   3 workspaces × 3 boards × 9 lists × 2–3 cards per list
 *
 * Users, email templates, sessions, jobs, etc. are left untouched so
 * existing logins (demo12@yopmail.com / password, etc.) keep working.
 *
 * Run with:
 *
 *   php artisan db:seed --class=FreshDemoSeeder
 */
final class FreshDemoSeeder extends Seeder
{
    /**
     * Tables holding workspace/board/card data that get truncated before
     * re-seeding. Order doesn't matter because FK checks are disabled
     * around the truncate loop, but children-before-parents is listed for
     * readability.
     *
     * @var list<string>
     */
    private const RESET_TABLES = [
        'activities',
        'notifications',
        'attachments',
        'checklist_items',
        'checklists',
        'comments',
        'card_label',
        'card_assignees',
        'cards',
        'labels',
        'lists',
        'board_members',
        'boards',
        'workspace_invitations',
        'workspace_members',
        'workspaces',
    ];

    /**
     * 3 workspaces. Each gets a list of 3 boards to seed.
     *
     * @var list<array{name: string, description: string, boards: list<string>}>
     */
    private const WORKSPACES = [
        [
            'name' => 'Engineering Hub',
            'description' => 'Primary workspace for product engineering work.',
            'boards' => ['Sprint Backlog', 'Bug Bash', 'Q1 Roadmap'],
        ],
        [
            'name' => 'Marketing Team',
            'description' => 'Campaigns, content calendar and growth experiments.',
            'boards' => ['Content Calendar', 'Launch Campaign', 'Growth Experiments'],
        ],
        [
            'name' => 'Design Studio',
            'description' => 'Product design, branding and visual assets.',
            'boards' => ['Brand Refresh', 'Mobile App v3', 'Design System'],
        ],
    ];

    /**
     * Sample card titles grouped by list name. The seeder picks 2–3 from
     * the matching bucket so cards "fit" the list they sit in.
     *
     * @var array<string, list<string>>
     */
    private const CARD_TITLES = [
        'Backlog' => [
            'Define project scope and milestones',
            'Research competitor analytics dashboards',
            'Draft new pricing page copy',
            'Audit accessibility on signup flow',
        ],
        'To Do' => [
            'Build login page',
            'Wire up password reset email',
            'Add 2FA setup screen',
            'Refactor billing service',
        ],
        'In Progress' => [
            'Design new homepage hero section',
            'Implement file upload to S3',
            'Migrate auth to Sanctum',
            'Optimize dashboard SQL queries',
        ],
        'UAT' => [
            'Review checkout page redesign',
            'QA the new permissions matrix',
            'Validate analytics events',
        ],
        'QA Testing' => [
            'Implement user authentication flow',
            'Set up CI/CD pipeline',
            'QA pass on contact form',
        ],
        'Released On Live' => [
            'Release v1.2 to production',
            'Hotfix deployed to live',
            'New pricing page live',
        ],
        'Completed' => [
            'Set up project repository',
            'Provision staging environment',
            'Publish v1 design tokens',
        ],
        'Hold Tasks' => [
            'Migrate legacy reports — blocked on data team',
            'Vendor onboarding — waiting on legal',
        ],
        'Closed Tasks' => [
            'Decommission old marketing site',
            'Sunset experimental feature flag',
        ],
        'Other Products' => [
            'Cross-team sync notes',
            'Shared component library updates',
        ],
    ];

    /**
     * Board label palette — kept in sync with the other seeders so the UI
     * looks consistent.
     *
     * @var list<array{name: string, color: string}>
     */
    private const LABELS = [
        ['name' => 'Bug',     'color' => '#eb5a46'],
        ['name' => 'Feature', 'color' => '#61bd4f'],
        ['name' => 'Urgent',  'color' => '#f2d600'],
        ['name' => 'Design',  'color' => '#c377e0'],
    ];

    /**
     * Board background colors, cycled across the boards we create.
     *
     * @var list<string>
     */
    private const BOARD_COLORS = [
        '#0079bf', '#d29034', '#519839', '#b04632', '#89609e',
        '#cd5a91', '#00aecc', '#838c91',
    ];

    public function run(): void
    {
        $this->wipeBoardData();

        DB::transaction(function (): void {
            $owner = $this->resolveOwner();
            $members = $this->resolveMemberPool();

            $boardColorIdx = 0;

            foreach (self::WORKSPACES as $wsSpec) {
                $workspace = $this->createWorkspace($wsSpec, $owner, $members);

                foreach ($wsSpec['boards'] as $boardName) {
                    $color = self::BOARD_COLORS[$boardColorIdx % count(self::BOARD_COLORS)];
                    $boardColorIdx++;

                    $board = $this->createBoard($workspace, $boardName, $color, $owner);
                    $this->seedListsAndCardsFor($board, $owner, $members);
                }
            }
        });

        $this->command->info('FreshDemoSeeder: reset board data and seeded 3 workspaces × 3 boards.');
    }

    /**
     * Truncate every workspace/board/card-related table so primary keys
     * restart from 1. FK checks are disabled around the loop because the
     * tables have circular references (e.g. `cards.board_id` <- `boards`
     * and `lists.board_id` <- `boards`) that would otherwise force a
     * fragile drop order.
     */
    private function wipeBoardData(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::RESET_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Find the demo user or create a fresh one. We never overwrite the
     * password / email_verified_at on an existing row so existing logins
     * keep working.
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
     * A small pool of additional users used as workspace members and card
     * assignees. Reuses any non-demo users already present and tops up to
     * at least 4 random users for variety.
     *
     * @return Collection<int, User>
     */
    private function resolveMemberPool(): Collection
    {
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
     * Create the workspace row and attach owner + members on the
     * `workspace_members` pivot.
     *
     * @param  array{name: string, description: string, boards: list<string>}  $spec
     * @param  Collection<int, User>  $members
     */
    private function createWorkspace(array $spec, User $owner, Collection $members): Workspace
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
     * Create a board + its default label palette.
     */
    private function createBoard(Workspace $workspace, string $name, string $color, User $owner): Board
    {
        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'description' => 'Track all '.strtolower($workspace->name).' work for the '.$name.' initiative.',
            'background_color' => $color,
            'visibility' => BoardVisibility::Workspace,
            'created_by' => $owner->id,
        ]);

        foreach (self::LABELS as $l) {
            Label::create([...$l, 'board_id' => $board->id]);
        }

        return $board;
    }

    /**
     * Create the standard Kanban list set for a board and populate each
     * list with a couple of relevant cards.
     *
     * @param  Collection<int, User>  $members
     */
    private function seedListsAndCardsFor(Board $board, User $owner, Collection $members): void
    {
        $listSpec = [
            ['name' => 'Backlog',          'stage' => ListStage::Backlog],
            ['name' => 'To Do',            'stage' => ListStage::Todo],
            ['name' => 'In Progress',      'stage' => ListStage::InProgress],
            ['name' => 'UAT',              'stage' => ListStage::Uat],
            ['name' => 'QA Testing',       'stage' => null],
            ['name' => 'Released On Live', 'stage' => null],
            ['name' => 'Completed',        'stage' => ListStage::Done],
            ['name' => 'Hold Tasks',       'stage' => null],
            ['name' => 'Closed Tasks',     'stage' => null],
            ['name' => 'Other Products',   'stage' => null],
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
     * Pick 2–3 sample card titles for the list and create matching cards.
     * The first card of each list also gets a random assignee so the
     * board has avatar chips to render.
     *
     * @param  Collection<int, User>  $members
     */
    private function seedCardsFor(BoardList $list, Board $board, User $owner, Collection $members): void
    {
        $titles = self::CARD_TITLES[$list->name] ?? ['Sample task'];
        $count = min(count($titles), random_int(2, 3));
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
