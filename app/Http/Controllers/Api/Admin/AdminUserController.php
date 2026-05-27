<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserAssignmentRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin surface for managing users + assigning them roles, workspaces
 * and boards. All endpoints are guarded by
 *   ->middleware('permission:users.manage')
 * at the route level.
 *
 * Workspace + board assignments REUSE the existing `workspace_members`
 * and `board_members` pivots so legacy "view / edit workspace" code
 * paths immediately see the assignment without a parallel ACL system.
 */
final class AdminUserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with(['roles:id,name'])
            ->withCount(['workspaces', 'boards'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 25));

        return AdminUserResource::collection($users);
    }

    public function show(User $user): AdminUserResource
    {
        $user->load([
            'roles:id,name',
            'workspaces:id,name',
            'boards:id,name,workspace_id',
        ]);

        return new AdminUserResource($user);
    }

    /**
     * Replace the user's global role set with the supplied list.
     * Empty list = strip every role.
     */
    public function syncRoles(UserAssignmentRequest $request, User $user): AdminUserResource
    {
        $user->syncRoles($request->validated()['roles'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->load(['roles:id,name', 'workspaces:id,name', 'boards:id,name,workspace_id']);

        return new AdminUserResource($user);
    }

    /**
     * Replace the user's workspace assignments. We sync the
     * workspace_members pivot directly because it already carries the
     * `role` (owner/admin/member) + `joined_at` columns the rest of the
     * app expects.
     *
     * @param  UserAssignmentRequest  $request
     */
    public function syncWorkspaces(UserAssignmentRequest $request, User $user): AdminUserResource
    {
        /** @var array<int, array{id: int, role: string}> $workspaces */
        $workspaces = $request->validated()['workspaces'] ?? [];

        $sync = [];
        foreach ($workspaces as $w) {
            $sync[(int) $w['id']] = [
                'role' => $w['role'],
                'joined_at' => now(),
            ];
        }

        DB::transaction(function () use ($user, $sync): void {
            $user->workspaces()->sync($sync);
        });

        $user->load(['roles:id,name', 'workspaces:id,name', 'boards:id,name,workspace_id']);

        return new AdminUserResource($user);
    }

    /**
     * Replace the user's board assignments. Similar shape to
     * syncWorkspaces, but writes to board_members.
     */
    public function syncBoards(UserAssignmentRequest $request, User $user): AdminUserResource
    {
        /** @var array<int, array{id: int, role: string}> $boards */
        $boards = $request->validated()['boards'] ?? [];

        $sync = [];
        foreach ($boards as $b) {
            $sync[(int) $b['id']] = [
                'role' => $b['role'],
            ];
        }

        DB::transaction(function () use ($user, $sync): void {
            $user->boards()->sync($sync);
        });

        $user->load(['roles:id,name', 'workspaces:id,name', 'boards:id,name,workspace_id']);

        return new AdminUserResource($user);
    }
}
