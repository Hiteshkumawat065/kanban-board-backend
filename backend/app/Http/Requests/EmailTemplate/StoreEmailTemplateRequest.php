<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailTemplate;

use App\Enums\EmailTemplateCategory;
use App\Enums\EmailTemplateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'template_name' => ['required', 'string', 'max:180'],
            'template_key' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9_.\-]+$/',
                Rule::unique('email_templates', 'template_key'),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                Rule::unique('email_templates', 'slug'),
            ],
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(EmailTemplateCategory::class)],
            'description' => ['nullable', 'string', 'max:2000'],

            'email_from_name' => ['nullable', 'string', 'max:180'],
            'email_from_email' => ['nullable', 'email', 'max:180'],
            'reply_to' => ['nullable', 'email', 'max:180'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
            'bcc' => ['nullable', 'array'],
            'bcc.*' => ['email'],

            'variables' => ['nullable', 'array'],
            'variables.*' => ['array'],
            'variables.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'variables.*.label' => ['nullable', 'string', 'max:120'],
            'variables.*.sample' => ['nullable', 'string', 'max:255'],

            'body_content' => ['required', 'string'],
            'plain_text' => ['nullable', 'string'],

            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.path' => ['required_with:attachments', 'string', 'max:500'],

            'status' => ['nullable', Rule::enum(EmailTemplateStatus::class)],
            'is_system_template' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', 'max:12'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalise list-typed inputs that may arrive as comma-separated
        // strings from the UI (the chips component sends arrays, but the
        // raw text input shape stays supported for power users / API).
        foreach (['cc', 'bcc'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $this->merge([
                    $field => collect(explode(',', $value))
                        ->map(fn ($v) => trim((string) $v))
                        ->filter()
                        ->values()
                        ->all(),
                ]);
            }
        }

        // Drop variable rows the user added but never filled in — the
        // variable palette lets you append blank rows, and shipping those
        // would otherwise fail the `regex` rule on `variables.*.key`.
        $variables = $this->input('variables');
        if (is_array($variables)) {
            $clean = collect($variables)
                ->filter(fn ($row) => is_array($row) && trim((string) ($row['key'] ?? '')) !== '')
                ->map(fn ($row) => [
                    'key' => trim((string) ($row['key'] ?? '')),
                    'label' => isset($row['label']) ? (string) $row['label'] : null,
                    'sample' => isset($row['sample']) ? (string) $row['sample'] : null,
                ])
                ->values()
                ->all();
            $this->merge(['variables' => $clean]);
        }
    }
}
