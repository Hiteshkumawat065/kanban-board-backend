<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Permission as PermEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionRequest;
use App\Http\Resources\PermissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin surface for the global permission catalog.
 *
 * Built-in permissions (every case in App\Enums\Permission) are seeded
 * by RolePermissionSeeder and protected from deletion here because
 * policies + controllers reference them by name.
 *
 * Admins can still mint *custom* permissions at runtime (e.g. for
 * project-specific features) — those custom rows are deletable.
 */
final class PermissionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::query()
            ->orderBy('name')
            ->get();

        return PermissionResource::collection($permissions);
    }

    public function store(PermissionRequest $request): JsonResponse
    {
        $permission = Permission::create([
            'name' => $request->string('name')->toString(),
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return (new PermissionResource($permission))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        if ($this->isBuiltIn($permission->name)) {
            return response()->json(
                ['message' => "The '{$permission->name}' permission is built-in and cannot be deleted."],
                422
            );
        }

        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Permission deleted.']);
    }

    private function isBuiltIn(string $name): bool
    {
        return PermEnum::tryFrom($name) !== null;
    }
}
