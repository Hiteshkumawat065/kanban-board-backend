<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\BoardResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WorkspacePageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $workspaces = $user
            ->workspaces()
            ->withCount('boards', 'members')
            ->orderBy('name')
            ->get();

        return Inertia::render('Workspaces/Index', [
            'workspaces' => WorkspaceResource::collection($workspaces),
        ]);
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $workspace->load('owner')->loadCount('boards', 'members');

        $boards = $workspace->boards()
            ->active()
            ->orderBy('position')
            ->get();

        return Inertia::render('Workspaces/Show', [
            'workspace' => new WorkspaceResource($workspace),
            'boards' => BoardResource::collection($boards),
        ]);
    }
}
