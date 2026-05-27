<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Card;
use App\Models\User;
use App\Services\PermissionService;

/**
 * Card-level authorization. Layers the new global permission set on top
 * of the existing "workspace member OR board member" scope, plus the
 * legacy mentor-can-approve-UAT shortcut so existing flows keep working.
 */
final class CardPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function view(User $user, Card $card): bool
    {
        return $this->permissions->canAccessBoard($user, $card->board);
    }

    public function create(User $user, Card $card): bool
    {
        if (! $this->permissions->canAccessBoard($user, $card->board)) {
            return false;
        }

        if ($user->can(Permission::CardsCreate->value)) {
            return true;
        }

        return $card->board->workspace->hasMember($user);
    }

    public function update(User $user, Card $card): bool
    {
        if (! $this->permissions->canAccessBoard($user, $card->board)) {
            return false;
        }

        if ($user->can(Permission::CardsUpdate->value)) {
            return true;
        }

        return $card->board->workspace->hasMember($user);
    }

    public function delete(User $user, Card $card): bool
    {
        if (! $this->permissions->canAccessBoard($user, $card->board)) {
            return false;
        }

        if ($user->can(Permission::CardsDelete->value)) {
            return true;
        }

        return $card->board->workspace->hasMember($user);
    }

    public function assign(User $user, Card $card): bool
    {
        if (! $this->permissions->canAccessBoard($user, $card->board)) {
            return false;
        }

        if ($user->can(Permission::CardsAssign->value)) {
            return true;
        }

        $role = $card->board->workspace->roleOf($user);

        return $role !== null && $role->canManageMembers();
    }

    /**
     * Only a mentor of one of the assignees (or a user with the explicit
     * cards.approve_uat permission, or a workspace Owner/Admin) can
     * approve UAT / request rework. This guarantees the dev cannot
     * approve their own work.
     */
    public function approveUat(User $user, Card $card): bool
    {
        $workspace = $card->board->workspace;

        if (! $this->permissions->canAccessBoard($user, $card->board)) {
            return false;
        }

        // Global Spatie permission takes precedence (e.g. Team Lead role
        // ships with cards.approve_uat by default).
        if ($user->can(Permission::CardsApproveUat->value)) {
            return true;
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
