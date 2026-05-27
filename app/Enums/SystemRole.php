<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The eight first-class global roles that ship with the app.
 *
 * Roles are stored in the `roles` table (Spatie\Permission\Models\Role) and
 * matched by name. Listing them in an enum lets controllers / seeders
 * reference roles without typo-prone string literals and lets the role
 * seeder mass-import the default set.
 *
 * Admins can create additional roles at runtime through the admin UI —
 * those will simply not appear in this enum, which is fine.
 */
enum SystemRole: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case TeamLead = 'Team Lead';
    case SeniorMentor = 'Senior/Mentor';
    case Developer = 'Developer';
    case Designer = 'Designer';
    case QA = 'QA';
    case Viewer = 'Viewer/Client';

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Full unrestricted access to every workspace, board, and admin surface.',
            self::Admin => 'High-level access to every workspace and board. Cannot manage roles/permissions.',
            self::TeamLead => 'Access to assigned workspaces and every board inside them.',
            self::SeniorMentor => 'Access to assigned boards. Can approve UAT for mentees.',
            self::Developer => 'Task-level access on assigned boards.',
            self::Designer => 'Task-level access on assigned boards (design lane).',
            self::QA => 'Testing-level access on assigned boards.',
            self::Viewer => 'Read-only access to explicitly shared workspaces / boards.',
        };
    }

    /**
     * Highest "access level" Super Admins bypass everything.
     * Used as a fast-path inside Gate::before — true means the user's
     * permission check is auto-granted without consulting the policy.
     */
    public function bypassesAuthorization(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Whether the role gets implicit access to ALL workspaces / boards
     * (i.e. doesn't need an explicit workspace_members / board_members
     * row to view them). Super Admin + Admin are global.
     */
    public function isGlobalScope(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
