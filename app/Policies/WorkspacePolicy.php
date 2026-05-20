<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

final class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        $role = $workspace->roleOf($user);

        return $role !== null && $role->canManageMembers();
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        $role = $workspace->roleOf($user);

        return $role !== null && $role->canDeleteWorkspace();
    }

    public function invite(User $user, Workspace $workspace): bool
    {
        $role = $workspace->roleOf($user);

        return $role !== null && $role->canManageMembers();
    }
}
