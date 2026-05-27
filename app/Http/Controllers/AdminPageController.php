<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SystemRole;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Renders the admin Inertia pages (Roles / Permissions / Users).
 *
 * Each method pre-loads everything the corresponding Vue page needs
 * so it can render without a follow-up XHR. Subsequent edits use the
 * `/api/v1/admin/*` JSON endpoints handled by the Admin\* controllers.
 *
 * Authorization is enforced by ->middleware('permission:...') on the
 * routes themselves (routes/web.php).
 */
final class AdminPageController extends Controller
{
    public function roles(): Response
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        $permissions = Permission::query()->orderBy('name')->get();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => RoleResource::collection($roles),
            'permissions' => PermissionResource::collection($permissions),
            'system_role_names' => SystemRole::values(),
        ]);
    }

    public function permissions(): Response
    {
        $permissions = Permission::query()->orderBy('name')->get();

        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => PermissionResource::collection($permissions),
        ]);
    }

    public function users(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with('roles:id,name')
            ->withCount(['workspaces', 'boards'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // The "Edit user" modal needs the full catalog of roles +
        // workspaces + boards to populate its selectors. Lightweight
        // lists (id + name only) keep the payload small even at scale.
        return Inertia::render('Admin/Users/Index', [
            'users' => AdminUserResource::collection($users),
            'filters' => ['search' => $search],
            'all_roles' => Role::orderBy('name')->pluck('name')->values(),
            'all_workspaces' => Workspace::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])
                ->values(),
            'all_boards' => Board::query()
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(['id', 'name', 'workspace_id'])
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'workspace_id' => $b->workspace_id,
                ])
                ->values(),
        ]);
    }
}
