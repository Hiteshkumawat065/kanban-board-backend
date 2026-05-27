<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canonical catalog of every permission key understood by the application.
 *
 * Permissions in the DB are matched by string (Spatie\Permission\Models\Permission).
 * Using an enum keeps controllers / policies type-safe, and lets the role
 * seeder mass-import the catalog with one foreach over self::cases().
 *
 * Add a new permission by:
 *   1. Adding the case here.
 *   2. Mapping it to roles in Database\Seeders\RolePermissionSeeder.
 *   3. Re-running `php artisan db:seed --class=RolePermissionSeeder`.
 *
 * Grouped by surface so the admin UI can render them in sections.
 */
enum Permission: string
{
    // ----- Workspaces ---------------------------------------------------
    case WorkspacesViewAll = 'workspaces.view_all';
    case WorkspacesCreate = 'workspaces.create';
    case WorkspacesUpdate = 'workspaces.update';
    case WorkspacesDelete = 'workspaces.delete';
    case WorkspacesManageMembers = 'workspaces.manage_members';

    // ----- Boards -------------------------------------------------------
    case BoardsViewAll = 'boards.view_all';
    case BoardsCreate = 'boards.create';
    case BoardsUpdate = 'boards.update';
    case BoardsDelete = 'boards.delete';
    case BoardsManageMembers = 'boards.manage_members';

    // ----- Cards / Tasks ------------------------------------------------
    case CardsCreate = 'cards.create';
    case CardsUpdate = 'cards.update';
    case CardsDelete = 'cards.delete';
    case CardsAssign = 'cards.assign';
    case CardsApproveUat = 'cards.approve_uat';

    // ----- Admin surfaces ----------------------------------------------
    case RolesManage = 'roles.manage';
    case PermissionsManage = 'permissions.manage';
    case UsersManage = 'users.manage';
    case EmailTemplatesManage = 'email_templates.manage';

    /**
     * Human-readable label for the admin UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::WorkspacesViewAll => 'View all workspaces',
            self::WorkspacesCreate => 'Create workspace',
            self::WorkspacesUpdate => 'Update workspace',
            self::WorkspacesDelete => 'Delete workspace',
            self::WorkspacesManageMembers => 'Manage workspace members',

            self::BoardsViewAll => 'View all boards',
            self::BoardsCreate => 'Create board',
            self::BoardsUpdate => 'Update board',
            self::BoardsDelete => 'Delete board',
            self::BoardsManageMembers => 'Manage board members',

            self::CardsCreate => 'Create card',
            self::CardsUpdate => 'Update card',
            self::CardsDelete => 'Delete card',
            self::CardsAssign => 'Assign users to card',
            self::CardsApproveUat => 'Approve UAT / request rework',

            self::RolesManage => 'Manage roles',
            self::PermissionsManage => 'Manage permissions',
            self::UsersManage => 'Manage users + assignments',
            self::EmailTemplatesManage => 'Manage email templates',
        };
    }

    /**
     * Surface grouping for the permissions admin UI.
     */
    public function group(): string
    {
        return match (true) {
            str_starts_with($this->value, 'workspaces.') => 'Workspaces',
            str_starts_with($this->value, 'boards.') => 'Boards',
            str_starts_with($this->value, 'cards.') => 'Cards',
            str_starts_with($this->value, 'email_templates.') => 'Email Templates',
            default => 'Administration',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
