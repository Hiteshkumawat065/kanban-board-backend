<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission as PermEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payloads for the three "assign X to user" endpoints exposed
 * by AdminUserController. One request class keeps things DRY since the
 * rule sets are tiny and very similar.
 */
final class UserAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermEnum::UsersManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Used by /admin/users/{user}/roles
            'roles' => ['sometimes', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],

            // Used by /admin/users/{user}/workspaces — full sync, so the
            // payload contains the *complete* set of workspace ids the
            // user should have access to. Anything missing is removed.
            'workspaces' => ['sometimes', 'array'],
            'workspaces.*.id' => ['required_with:workspaces', 'integer', 'exists:workspaces,id'],
            'workspaces.*.role' => ['required_with:workspaces', 'string', Rule::in(['owner', 'admin', 'member'])],

            // Used by /admin/users/{user}/boards
            'boards' => ['sometimes', 'array'],
            'boards.*.id' => ['required_with:boards', 'integer', 'exists:boards,id'],
            'boards.*.role' => ['required_with:boards', 'string', Rule::in(['admin', 'member', 'observer'])],
        ];
    }
}
