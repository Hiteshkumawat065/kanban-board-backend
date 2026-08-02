<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PermissionService;

/**
 * Two-layer authorization:
 *
 *   1. Super Admin bypass — Gate::before in AppServiceProvider returns
 *      true for Super Admins, so this class is never even called for them.
 *   2. Everyone else: global Spatie permission AND scope membership.
 *
 * Backward compatibility: the existing workspace_members.role enum
 * (Owner / Admin / Member) is still honored for the "manage members /
 * delete workspace" decisions so legacy data keeps working unchanged.
 */
final class WorkspacePolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->permissions->canAccessWorkspace($user, $workspace);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WorkspacesCreate->value);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        if (! $this->permissions->canAccessWorkspace($user, $workspace)) {
            return false;
        }

        // Global "workspaces.update" wins, OR the legacy workspace role
        // gives them management rights (keeps existing seeded data
        // working without forcing every Owner/Admin to also have the
        // new Spatie permission attached).
        if ($user->can(Permission::WorkspacesUpdate->value)) {
            return true;
        }

        $role = $workspace->roleOf($user);

        return $role !== null && $role->canManageMembers();
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        if (! $this->permissions->canAccessWorkspace($user, $workspace)) {
            return false;
        }

        if ($user->can(Permission::WorkspacesDelete->value)) {
            return true;
        }

        $role = $workspace->roleOf($user);

        return $role !== null && $role->canDeleteWorkspace();
    }

    public function invite(User $user, Workspace $workspace): bool
    {
        if (! $this->permissions->canAccessWorkspace($user, $workspace)) {
            return false;
        }

        if ($user->can(Permission::WorkspacesManageMembers->value)) {
            return true;
        }

        $role = $workspace->roleOf($user);

        return $role !== null && $role->canManageMembers();
    }
}
