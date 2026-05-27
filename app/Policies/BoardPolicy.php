<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Board;
use App\Models\User;
use App\Services\PermissionService;

/**
 * Board-level authorization. Super Admins bypass via Gate::before
 * (AppServiceProvider). For everyone else:
 *
 *   - view/update: scope access (workspace member OR board member OR
 *     global-scope role like Admin) is required.
 *   - create/delete: scope access AND the relevant global permission OR
 *     legacy workspace-role rights.
 *
 * The existing membership behavior is preserved as a fallback so
 * already-seeded boards keep working without forcing every existing
 * Owner/Admin to have the new permissions explicitly granted.
 */
final class BoardPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Board $board): bool
    {
        return $this->permissions->canAccessBoard($user, $board);
    }

    public function create(User $user, Board $board): bool
    {
        if (! $this->permissions->canAccessWorkspace($user, $board->workspace)) {
            return false;
        }

        if ($user->can(Permission::BoardsCreate->value)) {
            return true;
        }

        // Legacy behavior: any workspace member could create a board.
        return $board->workspace->hasMember($user);
    }

    public function update(User $user, Board $board): bool
    {
        if (! $this->permissions->canAccessBoard($user, $board)) {
            return false;
        }

        if ($user->can(Permission::BoardsUpdate->value)) {
            return true;
        }

        return $board->workspace->hasMember($user);
    }

    public function delete(User $user, Board $board): bool
    {
        if (! $this->permissions->canAccessBoard($user, $board)) {
            return false;
        }

        if ($user->can(Permission::BoardsDelete->value)) {
            return true;
        }

        $role = $board->workspace->roleOf($user);

        return $role !== null && $role->canManageMembers();
    }
}
