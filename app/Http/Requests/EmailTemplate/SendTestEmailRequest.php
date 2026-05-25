<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailTemplate;

use Illuminate\Foundation\Http\FormRequest;

final class SendTestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'to' => ['required', 'email', 'max:180'],
            'context' => ['nullable', 'array'],
        ];
    }
}
