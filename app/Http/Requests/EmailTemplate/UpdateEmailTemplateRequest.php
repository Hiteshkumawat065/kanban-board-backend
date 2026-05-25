<?php

declare(strict_types=1);

namespace App\Http\Requests\EmailTemplate;

use App\Enums\EmailTemplateCategory;
use App\Enums\EmailTemplateStatus;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $id = $this->template()?->id;

        return [
            'template_name' => ['sometimes', 'required', 'string', 'max:180'],
            'template_key' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9_.\-]+$/',
                Rule::unique('email_templates', 'template_key')->ignore($id),
            ],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:180',
                Rule::unique('email_templates', 'slug')->ignore($id),
            ],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::enum(EmailTemplateCategory::class)],
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

            'body_content' => ['sometimes', 'required', 'string'],
            'plain_text' => ['nullable', 'string'],

            'attachments' => ['nullable', 'array'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.path' => ['required_with:attachments', 'string', 'max:500'],

            'status' => ['sometimes', Rule::enum(EmailTemplateStatus::class)],
            'is_system_template' => ['sometimes', 'boolean'],
            'locale' => ['nullable', 'string', 'max:12'],
        ];
    }

    protected function prepareForValidation(): void
    {
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

        // Drop variable rows the user added but never filled in — see
        // StoreEmailTemplateRequest for the rationale.
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

    private function template(): ?EmailTemplate
    {
        $param = $this->route('email_template');

        return $param instanceof EmailTemplate ? $param : null;
    }
}
