<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $workspaces = $request->user()
            ->workspaces()
            ->withCount('boards', 'members')
            ->orderBy('name')
            ->get();

        return WorkspaceResource::collection($workspaces);
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->service->create($request->user(), $request->validated());

        return (new WorkspaceResource($workspace))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workspace $workspace): WorkspaceResource
    {
        $this->authorize('view', $workspace);

        // Eager-load members too so the "View details" modal on the
        // workspace listing screen can render the team composition
        // (developers / designers / QA / …) without an extra round-trip.
        $workspace->load('owner', 'members')->loadCount('boards', 'members');

        return new WorkspaceResource($workspace);
    }

    public function update(StoreWorkspaceRequest $request, Workspace $workspace): WorkspaceResource
    {
        $this->authorize('update', $workspace);
        $workspace->update($request->validated());

        return new WorkspaceResource($workspace);
    }

    public function destroy(Workspace $workspace): JsonResponse
    {
        $this->authorize('delete', $workspace);
        $workspace->delete();

        return response()->json(['message' => 'Workspace deleted']);
    }

    public function invite(InviteMemberRequest $request, Workspace $workspace): JsonResponse
    {
        $this->service->invite(
            $workspace,
            $request->user(),
            $request->string('email')->toString(),
            WorkspaceRole::from($request->string('role')->toString()),
        );

        return response()->json(['message' => 'Invitation sent'], 201);
    }
}
