<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Card;
use App\Models\User;

final class CardPolicy
{
    public function view(User $user, Card $card): bool
    {
        return $card->board->workspace->hasMember($user);
    }

    public function update(User $user, Card $card): bool
    {
        return $card->board->workspace->hasMember($user);
    }

    public function delete(User $user, Card $card): bool
    {
        return $card->board->workspace->hasMember($user);
    }

    /**
     * Only a mentor of one of the assignees (or a workspace owner/admin)
     * can approve UAT / request rework. This guarantees the dev cannot
     * approve their own work.
     */
    public function approveUat(User $user, Card $card): bool
    {
        $workspace = $card->board->workspace;

        if (! $workspace->hasMember($user)) {
            return false;
        }

        $role = $workspace->roleOf($user);

        if ($role !== null && $role->canManageMembers()) {
            return true;
        }

        // Mentor check: is this user a mentor of any current assignee?
        return $card->assignees()
            ->where('mentor_id', $user->id)
            ->exists();
    }
}
