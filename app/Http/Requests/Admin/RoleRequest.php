<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission as PermEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Validates payloads for creating / updating a role plus syncing its
 * permission list. One request class handles both endpoints because
 * the field surface is identical.
 */
final class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermEnum::RolesManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roleId = $this->route('role')?->id ?? null;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'web')
                    ->ignore($roleId),
            ],
            // The role-edit screen syncs permissions in the same payload.
            // Both keys are optional so a plain "create role" call (no
            // permissions yet) still validates.
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }
}
