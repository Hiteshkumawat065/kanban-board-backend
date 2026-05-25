<?php

declare(strict_types=1);

namespace App\Services\EmailTemplate;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Resolves `{{ variable }}` placeholders inside an email template at
 * send-time.
 *
 *   1. Application code calls {@see DynamicMailService::send()} with a
 *      `context` array (the structured per-send payload).
 *   2. The parser merges that context on top of a set of system defaults
 *      (current_date, company_name, …) and the template's declared
 *      `variables` (each variable can carry a `sample` value that powers
 *      the preview / test-send UX).
 *   3. Every `{{ key }}` token in the body & subject is replaced. Unknown
 *      tokens are left intact so an authoring mistake is visible in the
 *      preview rather than silently swallowed.
 *
 * The parser also exposes the canonical list of "globally available"
 * variables — surfaced in the Vue variable palette so authors don't
 * have to remember names by heart.
 */
final class VariableParser
{
    /**
     * Canonical set of placeholders the platform always exposes. The
     * frontend variable palette pulls this list (via the controller)
     * so the UI stays in sync with the backend without duplication.
     *
     * @return array<int, array{key: string, label: string, sample: string}>
     */
    public static function systemVariables(): array
    {
        $appName = (string) config('app.name', 'Kanban');
        $appUrl = (string) config('app.url', 'https://kanban.app');

        return [
            ['key' => 'user_name', 'label' => 'Recipient name', 'sample' => 'Jane Doe'],
            ['key' => 'user_email', 'label' => 'Recipient email', 'sample' => 'jane@example.com'],
            ['key' => 'app_name', 'label' => 'Application name', 'sample' => $appName],
            ['key' => 'app_url', 'label' => 'Application URL', 'sample' => $appUrl],
            ['key' => 'dashboard_link', 'label' => 'Dashboard link', 'sample' => $appUrl.'/dashboard'],
            ['key' => 'verification_link', 'label' => 'Verification link', 'sample' => $appUrl.'/verify/abc'],
            ['key' => 'reset_link', 'label' => 'Password reset link', 'sample' => $appUrl.'/reset/xyz'],
            ['key' => 'otp_code', 'label' => 'OTP code', 'sample' => '482917'],
            ['key' => 'otp_expiry', 'label' => 'OTP expiry (minutes)', 'sample' => '10'],
            ['key' => 'workspace_name', 'label' => 'Workspace name', 'sample' => 'Acme Corp'],
            ['key' => 'workspace_link', 'label' => 'Workspace link', 'sample' => $appUrl.'/workspaces/acme'],
            ['key' => 'board_name', 'label' => 'Board name', 'sample' => 'Sprint Board'],
            ['key' => 'board_link', 'label' => 'Board link', 'sample' => $appUrl.'/boards/sprint'],
            ['key' => 'task_title', 'label' => 'Task title', 'sample' => 'Build login page'],
            ['key' => 'task_link', 'label' => 'Task link', 'sample' => $appUrl.'/tasks/123'],
            ['key' => 'task_status', 'label' => 'Task status', 'sample' => 'In Progress'],
            ['key' => 'assigned_by', 'label' => 'Assigned by', 'sample' => 'Maya Sharma'],
            ['key' => 'due_date', 'label' => 'Due date', 'sample' => '2026-06-01'],
            ['key' => 'commented_by', 'label' => 'Commented by', 'sample' => 'Maya Sharma'],
            ['key' => 'comment_text', 'label' => 'Comment text', 'sample' => 'Looks great — shipping today.'],
            ['key' => 'comment_link', 'label' => 'Comment link', 'sample' => $appUrl.'/tasks/123#comment-7'],
            ['key' => 'current_date', 'label' => 'Current date', 'sample' => now()->toDateString()],
            // Legacy aliases — kept so older templates keep resolving.
            ['key' => 'company_name', 'label' => 'Company name (alias)', 'sample' => $appName],
        ];
    }

    /**
     * Built-in values that are always resolvable, even when application
     * code forgets to pass them in via `$context`.
     *
     * @return array<string, string>
     */
    public function systemDefaults(): array
    {
        $appName = (string) config('app.name', 'Kanban');
        $appUrl = (string) config('app.url', url('/'));

        return [
            'current_date' => now()->toFormattedDateString(),
            'current_year' => (string) now()->year,
            // `app_name` is the canonical placeholder used by every starter
            // (e.g. "{{ app_name }} Team"). `company_name` is kept as an
            // alias so older templates that referenced it keep working.
            'app_name' => $appName,
            'company_name' => $appName,
            'app_url' => $appUrl,
        ];
    }

    /**
     * Replace `{{ key }}` placeholders in `$content` using `$context`.
     *
     *   - `$context` wins over template-declared `sample` values which
     *     in turn win over system defaults.
     *   - Surrounding whitespace is tolerated:  `{{user_name}}` and
     *     `{{   user_name   }}` both resolve to the same key.
     *   - Unknown placeholders are preserved verbatim so authors can
     *     spot them in the live preview.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, array{key?: string, sample?: ?string}>|null  $variables
     */
    public function render(string $content, array $context = [], ?array $variables = null): string
    {
        $defaults = $this->systemDefaults();

        // User context (if the recipient or actor is the authenticated user
        // we can backfill `user_name` / `user_email` automatically).
        if (! array_key_exists('user_name', $context) && Auth::check()) {
            $defaults['user_name'] = (string) Auth::user()?->name;
        }
        if (! array_key_exists('user_email', $context) && Auth::check()) {
            $defaults['user_email'] = (string) Auth::user()?->email;
        }

        $samples = [];
        foreach ($variables ?? [] as $var) {
            if (isset($var['key']) && array_key_exists('sample', $var) && $var['sample'] !== null) {
                $samples[(string) $var['key']] = (string) $var['sample'];
            }
        }

        $merged = array_merge($defaults, $samples, $this->stringify($context));

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/u',
            function ($match) use ($merged) {
                $key = $match[1];

                return array_key_exists($key, $merged) ? $merged[$key] : $match[0];
            },
            $content,
        ) ?? $content;
    }

    /**
     * Convenience wrapper that renders both subject + body of a template
     * and returns them as a tuple.
     *
     * @param  array<string, mixed>  $context
     * @return array{subject: string, body: string, plain_text: ?string}
     */
    public function renderTemplate(EmailTemplate $template, array $context = []): array
    {
        $variables = $template->variables ?? [];

        return [
            'subject' => $this->render($template->subject, $context, $variables),
            'body' => $this->render($template->body_content, $context, $variables),
            'plain_text' => $template->plain_text
                ? $this->render($template->plain_text, $context, $variables)
                : null,
        ];
    }

    /**
     * Best-effort cast of arbitrary context values to strings so they
     * can be substituted into the body. Arrays/objects are flattened
     * via Str so a model accidentally passed in still produces readable
     * output instead of "Array".
     *
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function stringify(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out[(string) $k] = (string) ($v ?? '');

                continue;
            }
            if ($v instanceof \DateTimeInterface) {
                $out[(string) $k] = $v->format('Y-m-d H:i');

                continue;
            }
            if (is_array($v) || is_object($v)) {
                $out[(string) $k] = (string) Str::of(json_encode($v) ?: '')->limit(200);

                continue;
            }
            $out[(string) $k] = (string) $v;
        }

        return $out;
    }
}
