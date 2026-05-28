<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard landing page — the first screen an authenticated user lands on.
 *
 * Aggregates the panels that the Dashboard.vue page renders:
 *   1. Top stat row          — workspaces / boards / members totals.
 *   2. Recent workspaces     — last 8 workspaces the user joined or updated.
 *   3. Recent boards         — the 8 most recently updated boards across all
 *                              workspaces the user is a member of.
 *   4. Recent comments       — the latest 10 comments on cards in those
 *                              workspaces, with author + card + board context.
 *   5. Closed boards preview — first 5 archived boards plus a total count;
 *                              the "View all" modal lazy-loads the rest from
 *                              GET /api/v1/boards/closed.
 *
 * Everything is scoped to "workspaces the user is a member of" so unrelated
 * organizations stay invisible. Stat counts intentionally exclude archived
 * boards / soft-deleted rows to match what the kanban UI actually shows.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // IDs of workspaces the user is a member of — reused across queries
        // so we only hit `workspace_members` once.
        $workspaceIds = $user->workspaces()->pluck('workspaces.id')->all();

        // --- 1. Stat tiles --------------------------------------------------
        // Total workspaces the user belongs to. Using count() on the pre-fetched
        // array avoids a redundant DB round trip (we already pulled the IDs above).
        $workspacesCount = count($workspaceIds);

        // Use the RBAC-aware visibleTo() scope so the stat tile reflects
        // what the user can actually open. A Developer assigned to 1 board
        // in a 10-board workspace will see "1", not "10".
        $boardsCount = Board::visibleTo($user)
            ->whereNull('archived_at')
            ->count();

        // Distinct collaborator count across all the user's workspaces (the
        // same human shows up once even if they're in many workspaces).
        $membersCount = DB::table('workspace_members')
            ->whereIn('workspace_id', $workspaceIds)
            ->distinct()
            ->count('user_id');

        // --- 2. Recent workspaces ------------------------------------------
        // Ordered by the pivot's `updated_at` so a workspace bubbles to the
        // top whenever the user does anything that touches its membership
        // (joined, role changed, etc.). Falls back to the workspace's own
        // updated_at as a tie-breaker.
        //
        // The per-workspace `boards_count` is also scoped to visibleTo()
        // so the tile shows "you can open 2 boards" instead of "the
        // workspace has 10 boards" for a Developer-style user.
        $recentWorkspaces = $user->workspaces()
            ->withCount(['boards' => fn ($q) => $q
                ->whereNull('archived_at')
                ->visibleTo($user)])
            ->orderByDesc('workspace_members.updated_at')
            ->orderByDesc('workspaces.updated_at')
            ->limit(8)
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'name' => $w->name,
                'description' => $w->description,
                'boards_count' => (int) $w->boards_count,
                'updated_at' => $w->updated_at?->diffForHumans(),
            ])
            ->values();

        // --- 3. Recent boards (most recently updated) ----------------------
        // RBAC-scoped via visibleTo(): functional roles only see boards
        // they were explicitly assigned to; managers see everything in
        // their workspaces; global-scope roles see everything.
        $recentBoards = Board::visibleTo($user)
            ->whereNull('archived_at')
            ->with('workspace:id,name')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'background_color' => $b->background_color,
                'workspace_name' => $b->workspace?->name,
                'workspace_id' => $b->workspace_id,
                'updated_at' => $b->updated_at?->diffForHumans(),
            ])
            ->values();

        // --- 4. Recent comments --------------------------------------------
        // Scoped to comments on cards whose board the user can actually
        // open (so a Developer doesn't leak chatter from boards they
        // aren't a member of). Eager-loads author + card + board so we
        // can render "Hitesh on 'Build login page' (CRM Development)"
        // without N+1 queries.
        $recentComments = Comment::whereHas(
            'card.board',
            fn ($q) => $q->visibleTo($user)
                ->whereNull('archived_at')
        )
            ->with([
                'author:id,name,email',
                'card:id,title,board_id',
                'card.board:id,name',
            ])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                // Trim long comment bodies so a single screed doesn't blow
                // out the panel height — the full text is visible on the card.
                'body' => Str::limit((string) $c->body, 140),
                'user_id' => $c->user_id,
                'user_name' => $c->author?->name ?? 'Someone',
                'card_id' => $c->card_id,
                'card_title' => $c->card?->title,
                'board_id' => $c->card?->board_id,
                'board_name' => $c->card?->board?->name,
                'when' => $c->created_at?->diffForHumans(),
            ])
            ->values();

        // --- 5. Closed boards preview --------------------------------------
        // Only the first 5 archived boards ship with the dashboard payload;
        // the modal's "View all" fetches the full list lazily from
        // BoardController@closed so we don't bloat the initial page load
        // for users with many archived boards. visibleTo() makes sure the
        // preview only contains boards the user could re-open.
        $closedBoardsBase = Board::visibleTo($user)
            ->whereNotNull('archived_at');

        $closedBoardsCount = (clone $closedBoardsBase)->count();

        $closedBoardsPreview = $closedBoardsBase
            ->with('workspace:id,name')
            ->orderByDesc('archived_at')
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'background_color' => $b->background_color,
                'workspace_name' => $b->workspace?->name,
                'workspace_id' => $b->workspace_id,
                'archived_at' => $b->archived_at?->diffForHumans(),
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'stats' => [
                'workspaces' => $workspacesCount,
                'boards' => $boardsCount,
                'members' => $membersCount,
            ],
            'recent_workspaces' => $recentWorkspaces,
            'recent_boards' => $recentBoards,
            'recent_comments' => $recentComments,
            'closed_boards_preview' => $closedBoardsPreview,
            'closed_boards_count' => $closedBoardsCount,
        ]);
    }
}
