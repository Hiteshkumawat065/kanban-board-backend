<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\WorkspaceResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WorkspacePageController extends Controller
{
    /**
     * Workspace listing page — renders the top-level "Your Workspaces" grid.
     * The board listing for a single workspace now lives under
     * BoardPageController@index at /workspaces/{workspace}/boards.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $workspaces = $user
            ->workspaces()
            ->withCount('boards', 'members')
            // Show newest first so a freshly-created workspace appears at
            // the top of the listing screen.
            ->orderByDesc('workspaces.created_at')
            ->get();

        return Inertia::render('Workspaces/Index', [
            'workspaces' => WorkspaceResource::collection($workspaces),
        ]);
    }
}
