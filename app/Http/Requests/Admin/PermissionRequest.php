<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Permission as PermEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a permission slug at runtime.
 * Slugs are forced to dot.snake_case so they group cleanly in the UI
 * (e.g. "reports.export", "invoices.refund").
 */
final class PermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermEnum::PermissionsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:160',
                'regex:/^[a-z0-9_]+\.[a-z0-9_.]+$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Permission name must be in dot.snake_case form, e.g. "reports.export".',
        ];
    }
}
