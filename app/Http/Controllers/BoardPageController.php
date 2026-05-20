<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\BoardResource;
use App\Models\Board;
use Inertia\Inertia;
use Inertia\Response;

final class BoardPageController extends Controller
{
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
