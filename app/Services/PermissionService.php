<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Permission;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;

/**
 * Central authorization helper.
 *
 * Policies + controllers delegate here so the "global permission + scope"
 * decision lives in one place. Two layers:
 *
 *  1. Global permission check (Spatie\Permission). E.g. does the user have
 *     `boards.update`? If not, denied.
 *  2. Resource-scope check. Even if the user has `boards.update` globally,
 *     they must also have access to the specific board (via global-scope
 *     role like Super Admin/Admin, or via workspace_members /
 *     board_members rows).
 */
final class PermissionService
{
    public function userHasPermission(User $user, Permission|string $permission): bool
    {
        $value = $permission instanceof Permission ? $permission->value : $permission;

        return $user->can($value);
    }

    /**
     * Does the user have *any* read access to this workspace? True if:
     *  - User has a global-scope role (Super Admin / Admin), OR
     *  - User is a member of the workspace (existing workspace_members row).
     */
    public function canAccessWorkspace(User $user, Workspace $workspace): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        return $workspace->hasMember($user);
    }

    /**
     * Does the user have *any* read access to this board? True if:
     *  - User has a global-scope role (Super Admin / Admin), OR
     *  - User is a member of the board's workspace, OR
     *  - User is explicitly attached to the board via board_members.
     */
    public function canAccessBoard(User $user, Board $board): bool
    {
        if ($user->hasGlobalAccess()) {
            return true;
        }

        if ($board->workspace->hasMember($user)) {
            return true;
        }

        return $board->members()->where('users.id', $user->id)->exists();
    }

    /**
     * Combined check: user has the named permission AND has scope access
     * to the workspace.
     */
    public function canOnWorkspace(User $user, Permission|string $permission, Workspace $workspace): bool
    {
        return $this->userHasPermission($user, $permission)
            && $this->canAccessWorkspace($user, $workspace);
    }

    /**
     * Combined check: user has the named permission AND has scope access
     * to the board.
     */
    public function canOnBoard(User $user, Permission|string $permission, Board $board): bool
    {
        return $this->userHasPermission($user, $permission)
            && $this->canAccessBoard($user, $board);
    }
}
