<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invite', $this->route('workspace'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc,dns', 'max:180'],
            'role' => [
                'required',
                new Enum(WorkspaceRole::class),
                Rule::notIn([WorkspaceRole::Owner->value]),
            ],
        ];
    }
}
