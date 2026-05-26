<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Board;
use App\Models\User;

final class BoardPolicy
{
    public function view(User $user, Board $board): bool
    {
        return $board->workspace->hasMember($user);
    }

    public function create(User $user, Board $board): bool
    {
        return $board->workspace->hasMember($user);
    }

    public function update(User $user, Board $board): bool
    {
        return $board->workspace->hasMember($user);
    }

    public function delete(User $user, Board $board): bool
    {
        $role = $board->workspace->roleOf($user);

        return $role !== null && $role->canManageMembers();
    }
}
