<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ListStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBoardRequest;
use App\Http\Resources\BoardResource;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

final class BoardController extends Controller
{
    /**
     * Default lists every brand-new board ships with so users see the
     * same workflow columns (Backlog -> Done) across every workspace.
     * Stages are pinned so card-workflow logic (CardService) keeps working
     * even if a user renames a list later.
     *
     * @var list<array{name: string, stage: ListStage|null}>
     */
    private const DEFAULT_LISTS = [
        ['name' => 'Backlog', 'stage' => ListStage::Backlog],
        ['name' => 'To Do', 'stage' => ListStage::Todo],
        ['name' => 'In Progress', 'stage' => ListStage::InProgress],
        ['name' => 'UAT', 'stage' => ListStage::Uat],
        ['name' => 'Ready To Test', 'stage' => null],
        ['name' => 'Completed', 'stage' => ListStage::Done],
        ['name' => 'Hold Tasks', 'stage' => null],
        ['name' => 'Closed Task', 'stage' => null],
        ['name' => 'Other Products', 'stage' => null],
    ];

    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('view', $workspace);

        $boards = $workspace->boards()
            ->active()
            ->orderBy('position')
            ->get();

        return BoardResource::collection($boards);
    }

    /**
     * All archived ("closed") boards across every workspace the user belongs
     * to. Powers the "View all closed boards" modal on the dashboard, so it
     * intentionally ignores the per-workspace scope and instead filters by
     * membership of the authenticated user.
     *
     * Ordered by most-recently-archived first since users typically want to
     * find something they just closed, not something archived years ago.
     */
    public function closed(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $workspaceIds = $user->workspaces()->pluck('workspaces.id')->all();

        $boards = Board::whereIn('workspace_id', $workspaceIds)
            ->whereNotNull('archived_at')
            ->with('workspace:id,name')
            ->orderByDesc('archived_at')
            ->get();

        return BoardResource::collection($boards);
    }

    public function store(StoreBoardRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $board = DB::transaction(function () use ($request, $workspace): Board {
            $board = $workspace->boards()->create([
                ...$request->validated(),
                'created_by' => $request->user()->id,
            ]);

            $position = 1.0;
            foreach (self::DEFAULT_LISTS as $spec) {
                BoardList::create([
                    'board_id' => $board->id,
                    'name' => $spec['name'],
                    'stage' => $spec['stage']?->value,
                    'position' => $position,
                ]);
                $position += 1.0;
            }

            return $board;
        });

        return (new BoardResource($board))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Board $board): BoardResource
    {
        $this->authorize('view', $board);

        $board->load([
            'creator',
            'labels',
            'lists' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'),
            'lists.cards' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'),
            'lists.cards.assignees',
            'lists.cards.labels',
        ])->loadCount(['lists.cards as comments_count' => fn ($q) => $q]);

        return new BoardResource($board);
    }

    public function update(StoreBoardRequest $request, Board $board): BoardResource
    {
        $this->authorize('update', $board);
        $board->update($request->validated());

        return new BoardResource($board);
    }

    public function destroy(Board $board): JsonResponse
    {
        $this->authorize('delete', $board);
        $board->delete();

        return response()->json(['message' => 'Board deleted']);
    }
}
