<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin CRUD for roles + role <-> permission sync.
 *
 * All endpoints are guarded by ->middleware('permission:roles.manage')
 * at the route level (routes/api.php), so the constructor doesn't need
 * to repeat the check.
 *
 * Built-in roles from App\Enums\SystemRole are protected against
 * rename / delete because policies elsewhere check them by name.
 */
final class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return RoleResource::collection($roles);
    }

    public function show(Role $role): RoleResource
    {
        $role->load('permissions:id,name')->loadCount('users');

        return new RoleResource($role);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = DB::transaction(function () use ($data): Role {
            $role = Role::create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            if (! empty($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return (new RoleResource($role->fresh('permissions')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(RoleRequest $request, Role $role): RoleResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($role, $data): void {
            // Built-in role names are read-only because policies key
            // off them. Admins can still edit the permission grants.
            if (! $this->isBuiltIn($role->name) && isset($data['name'])) {
                $role->update(['name' => $data['name']]);
            }

            if (array_key_exists('permissions', $data)) {
                $role->syncPermissions($data['permissions'] ?? []);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return new RoleResource($role->fresh('permissions'));
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($this->isBuiltIn($role->name)) {
            return response()->json(
                ['message' => "The '{$role->name}' role is built-in and cannot be deleted."],
                422
            );
        }

        if ($role->users()->exists()) {
            return response()->json(
                ['message' => 'Cannot delete a role that is still assigned to users.'],
                422
            );
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Role deleted.']);
    }

    /**
     * Explicit "sync permissions" endpoint for the role-edit screen so
     * the frontend can save permission changes without re-sending the
     * role name (avoids unique-name validation errors).
     */
    public function syncPermissions(RoleRequest $request, Role $role): RoleResource
    {
        $role->syncPermissions($request->validated()['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return new RoleResource($role->fresh('permissions'));
    }

    private function isBuiltIn(string $name): bool
    {
        return SystemRole::tryFrom($name) !== null;
    }
}
