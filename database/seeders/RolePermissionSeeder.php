<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission as PermEnum;
use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the canonical RBAC dataset:
 *
 *  - Every permission listed in App\Enums\Permission.
 *  - The eight first-class roles listed in App\Enums\SystemRole.
 *  - The default permission grants per role (rules table below).
 *
 * Idempotent: re-running upgrades existing rows + adds anything new in
 * the catalog without nuking custom roles/permissions an admin created
 * through the UI.
 *
 * Also makes sure demo12@yopmail.com (and mentor12@yopmail.com) keep
 * working out-of-the-box by granting them Super Admin / Admin roles
 * respectively. Any other user keeps whatever role they already have.
 */
final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Re-create permission cache so the next role->givePermissionTo()
        // sees the freshly-inserted rows.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $this->seedPermissions();
            $this->seedRoles();
            $this->assignPermissionsToRoles();
            $this->bootstrapDefaultUsers();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command->info('RolePermissionSeeder: roles + permissions are up to date.');
    }

    private function seedPermissions(): void
    {
        foreach (PermEnum::cases() as $perm) {
            Permission::findOrCreate($perm->value, 'web');
        }
    }

    private function seedRoles(): void
    {
        foreach (SystemRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    /**
     * Default permission matrix. Anything not listed here for a role is
     * implicitly denied (until an admin opts in via the UI).
     */
    private function assignPermissionsToRoles(): void
    {
        /** @var array<string, list<PermEnum>> $matrix */
        $matrix = [
            // Super Admin auto-bypasses everything via Gate::before, but
            // we still grant the full set so role-detail screens show
            // "everything ticked" as the source of truth.
            SystemRole::SuperAdmin->value => PermEnum::cases(),

            // Admin = full workspace/board/card access + manages users +
            // email templates. Cannot manage roles/permissions (that's
            // reserved for Super Admin so an Admin can't escalate).
            SystemRole::Admin->value => [
                PermEnum::WorkspacesViewAll, PermEnum::WorkspacesCreate, PermEnum::WorkspacesUpdate,
                PermEnum::WorkspacesDelete, PermEnum::WorkspacesManageMembers,
                PermEnum::BoardsViewAll, PermEnum::BoardsCreate, PermEnum::BoardsUpdate,
                PermEnum::BoardsDelete, PermEnum::BoardsManageMembers,
                PermEnum::CardsCreate, PermEnum::CardsUpdate, PermEnum::CardsDelete,
                PermEnum::CardsAssign, PermEnum::CardsApproveUat,
                PermEnum::UsersManage, PermEnum::EmailTemplatesManage,
            ],

            // Team Lead = full access inside their assigned workspaces +
            // inside every board of those workspaces. Cannot view *all*
            // workspaces / boards globally (scope handled by policies).
            SystemRole::TeamLead->value => [
                PermEnum::WorkspacesUpdate, PermEnum::WorkspacesManageMembers,
                PermEnum::BoardsCreate, PermEnum::BoardsUpdate, PermEnum::BoardsDelete,
                PermEnum::BoardsManageMembers,
                PermEnum::CardsCreate, PermEnum::CardsUpdate, PermEnum::CardsDelete,
                PermEnum::CardsAssign, PermEnum::CardsApproveUat,
            ],

            // Senior / Mentor = card-level work + UAT approval, on
            // assigned boards only.
            SystemRole::SeniorMentor->value => [
                PermEnum::CardsCreate, PermEnum::CardsUpdate,
                PermEnum::CardsAssign, PermEnum::CardsApproveUat,
            ],

            // Developer / Designer / QA all share the same "create + edit
            // cards on assigned boards" baseline. The functional split
            // is enforced by job_title + workspace UI, not permissions.
            SystemRole::Developer->value => [
                PermEnum::CardsCreate, PermEnum::CardsUpdate,
            ],
            SystemRole::Designer->value => [
                PermEnum::CardsCreate, PermEnum::CardsUpdate,
            ],
            SystemRole::QA->value => [
                PermEnum::CardsCreate, PermEnum::CardsUpdate,
            ],

            // Viewer / Client = read only. They have no mutating
            // permissions; the scope check still gates which workspaces
            // / boards they see.
            SystemRole::Viewer->value => [],
        ];

        foreach ($matrix as $roleName => $perms) {
            $role = Role::findByName($roleName, 'web');

            $permissionValues = array_map(
                static fn (PermEnum $p) => $p->value,
                $perms
            );

            // syncPermissions replaces the whole set so the seeder
            // remains the source of truth for the *default* matrix.
            $role->syncPermissions($permissionValues);
        }
    }

    /**
     * Grant the seeded demo accounts a sensible role so the existing
     * "log in as demo12" flow keeps working unchanged.
     */
    private function bootstrapDefaultUsers(): void
    {
        $assignments = [
            'demo12@yopmail.com' => SystemRole::SuperAdmin->value,
            'mentor12@yopmail.com' => SystemRole::Admin->value,
        ];

        foreach ($assignments as $email => $roleName) {
            $user = User::where('email', $email)->first();

            if ($user && ! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        }
    }
}
