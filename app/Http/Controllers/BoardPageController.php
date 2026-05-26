<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\BoardResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Board;
use App\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

final class BoardPageController extends Controller
{
    /**
     * Board listing page — every active board inside the given workspace.
     * Lives at /workspaces/{workspace}/boards (route name: boards.index).
     */
    public function index(Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        // Eager-load members so WorkspaceResource can compute `team_counts`
        // (Developers / Designers / QA / Manager) and expose the full
        // grouped list for the "View Details" modal on each board tile.
        $workspace->load(['owner', 'members'])->loadCount('boards', 'members');

        $boards = $workspace->boards()
            ->active()
            // Newest board first — matches the workspace listing behavior
            // so users always see their most recent work at the top.
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Boards/Index', [
            'workspace' => new WorkspaceResource($workspace),
            'boards' => BoardResource::collection($boards),
        ]);
    }

    public function show(Board $board): Response
    {
        $this->authorize('view', $board);

        $board->load([
            'creator',
            'labels',
            'workspace.members',
            'lists' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'),
            'lists.cards.assignees',
            'lists.cards.labels',
        ]);

        return Inertia::render('Boards/Show', [
            'board' => new BoardResource($board),
        ]);
    }
}
