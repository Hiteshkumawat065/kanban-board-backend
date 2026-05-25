<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EmailTemplateCategory;
use App\Enums\EmailTemplateStatus;
use App\Http\Requests\EmailTemplate\SendTestEmailRequest;
use App\Http\Requests\EmailTemplate\StoreEmailTemplateRequest;
use App\Http\Requests\EmailTemplate\UpdateEmailTemplateRequest;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\EmailTemplate\DynamicMailService;
use App\Services\EmailTemplate\EmailTemplateService;
use App\Services\EmailTemplate\VariableParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inertia-driven CRUD for email templates. JSON-only endpoints (preview,
 * send test, logs) return JsonResponse instances; everything else returns
 * full Inertia pages or RedirectResponse via the PRG pattern.
 */
final class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly EmailTemplateService $service,
        private readonly DynamicMailService $mailer,
        private readonly VariableParser $parser,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['q', 'status', 'category', 'sort', 'direction', 'per_page']);
        $paginator = $this->service->paginate($filters);

        // Flat per-row payload — Inertia serialises this directly and the
        // Vue table consumes it without an extra resource layer.
        $items = collect($paginator->items())->map(fn (EmailTemplate $t) => [
            'id' => $t->id,
            'template_name' => $t->template_name,
            'template_key' => $t->template_key,
            'slug' => $t->slug,
            'subject' => $t->subject,
            'category' => $t->category->value,
            'category_label' => $t->category->label(),
            'category_color' => $t->category->color(),
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'status_color' => $t->status->color(),
            'is_system_template' => $t->is_system_template,
            'updated_at' => $t->updated_at?->diffForHumans(),
            'updated_at_iso' => $t->updated_at?->toIso8601String(),
            'updated_by_name' => $t->updater?->name,
            'created_by_name' => $t->creator?->name,
            'version' => $t->version,
        ])->values();

        $stats = [
            'total' => EmailTemplate::query()->count(),
            'active' => EmailTemplate::query()->where('status', EmailTemplateStatus::Active->value)->count(),
            'inactive' => EmailTemplate::query()->where('status', EmailTemplateStatus::Inactive->value)->count(),
            'draft' => EmailTemplate::query()->where('status', EmailTemplateStatus::Draft->value)->count(),
        ];

        return Inertia::render('EmailTemplates/Index', [
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'q' => (string) ($filters['q'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'category' => (string) ($filters['category'] ?? ''),
                'sort' => (string) ($filters['sort'] ?? 'updated_at'),
                'direction' => (string) ($filters['direction'] ?? 'desc'),
                'per_page' => (int) ($filters['per_page'] ?? 15),
            ],
            'stats' => $stats,
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('EmailTemplates/Form', [
            'mode' => 'create',
            'template' => $this->emptyTemplatePayload(),
            'options' => $this->options(),
        ]);
    }

    public function store(StoreEmailTemplateRequest $request): RedirectResponse
    {
        $template = $this->service->create($request->validated(), $request->user());

        return redirect()
            ->route('email-templates.edit', $template)
            ->with('flash', ['type' => 'success', 'message' => 'Email template created.']);
    }

    public function edit(EmailTemplate $emailTemplate): Response
    {
        return Inertia::render('EmailTemplates/Form', [
            'mode' => 'edit',
            'template' => $this->templatePayload($emailTemplate),
            'options' => $this->options(),
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->service->update($emailTemplate, $request->validated(), $request->user());

        return redirect()
            ->route('email-templates.edit', $emailTemplate)
            ->with('flash', ['type' => 'success', 'message' => 'Email template updated.']);
    }

    public function destroy(EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->service->delete($emailTemplate);

        return redirect()
            ->route('email-templates.index')
            ->with('flash', ['type' => 'success', 'message' => 'Email template deleted.']);
    }

    /**
     * Render the email body (HTML) against caller-supplied variables and
     * return it as JSON. Used by the live-preview iframe in the editor so
     * the author sees what their template looks like in real time without
     * a full page reload.
     *
     * The request can override `subject`, `body` and `variables` so the
     * preview reflects the in-flight DRAFT the user is editing rather than
     * whatever's currently persisted on the model. When an override is
     * omitted we fall back to the saved column — handy for the listing's
     * "send test" flow that doesn't go through the editor.
     */
    public function preview(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $context = (array) $request->input('context', []);

        $subject = $request->has('subject') ? (string) $request->input('subject') : $emailTemplate->subject;
        $body = $request->has('body') ? (string) $request->input('body') : $emailTemplate->body_content;
        $variables = $request->has('variables')
            ? (array) $request->input('variables', [])
            : ($emailTemplate->variables ?? []);

        return response()->json([
            'subject' => $this->parser->render($subject, $context, $variables),
            'body' => $this->parser->render($body, $context, $variables),
            'plain_text' => $emailTemplate->plain_text
                ? $this->parser->render($emailTemplate->plain_text, $context, $variables)
                : null,
        ]);
    }

    public function sendTest(SendTestEmailRequest $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $log = $this->mailer->sendTest(
            $emailTemplate,
            $request->string('to')->toString(),
            (array) $request->input('context', []),
            $request->user(),
        );

        return response()->json([
            'status' => $log->status,
            'error' => $log->error,
            'log_id' => $log->id,
        ], $log->status === 'failed' ? 422 : 200);
    }

    public function duplicate(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $copy = $this->service->duplicate($emailTemplate, $request->user());

        return redirect()
            ->route('email-templates.edit', $copy)
            ->with('flash', ['type' => 'success', 'message' => 'Template duplicated.']);
    }

    public function toggleStatus(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->service->toggleStatus($emailTemplate, $request->user());

        return back()->with('flash', ['type' => 'success', 'message' => 'Status updated.']);
    }

    public function logs(EmailTemplate $emailTemplate): Response
    {
        $logs = $emailTemplate->logs()
            ->with('sender:id,name')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('EmailTemplates/Logs', [
            'template' => [
                'id' => $emailTemplate->id,
                'slug' => $emailTemplate->slug,
                'template_name' => $emailTemplate->template_name,
                'template_key' => $emailTemplate->template_key,
            ],
            'items' => collect($logs->items())->map(fn (EmailLog $l) => [
                'id' => $l->id,
                'to_email' => $l->to_email,
                'subject' => $l->subject,
                'status' => $l->status,
                'error' => $l->error,
                'created_at' => $l->created_at?->diffForHumans(),
                'sent_at' => $l->sent_at?->diffForHumans(),
                'sender_name' => $l->sender?->name,
            ])->values(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // Shared payload helpers
    // ---------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'categories' => EmailTemplateCategory::options(),
            'statuses' => EmailTemplateStatus::options(),
            'system_variables' => VariableParser::systemVariables(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTemplatePayload(): array
    {
        return [
            'id' => null,
            'template_name' => '',
            'template_key' => '',
            'slug' => '',
            'subject' => '',
            'category' => EmailTemplateCategory::Notification->value,
            'description' => '',
            'email_from_name' => null,
            'email_from_email' => null,
            'reply_to' => null,
            'cc' => [],
            'bcc' => [],
            'variables' => [],
            'body_content' => '<p>Hello {{ user_name }},</p><p>Write your message here…</p><p>Regards,<br><strong>{{ app_name }} Team</strong></p>',
            'plain_text' => null,
            'attachments' => [],
            'status' => EmailTemplateStatus::Active->value,
            'is_system_template' => false,
            'locale' => 'en',
            'version' => 1,
            'updated_at' => null,
            'created_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(EmailTemplate $t): array
    {
        return [
            'id' => $t->id,
            'template_name' => $t->template_name,
            'template_key' => $t->template_key,
            'slug' => $t->slug,
            'subject' => $t->subject,
            'category' => $t->category->value,
            'description' => $t->description,
            'email_from_name' => $t->email_from_name,
            'email_from_email' => $t->email_from_email,
            'reply_to' => $t->reply_to,
            'cc' => $t->cc ?? [],
            'bcc' => $t->bcc ?? [],
            'variables' => $t->variables ?? [],
            'body_content' => $t->body_content,
            'plain_text' => $t->plain_text,
            'attachments' => $t->attachments ?? [],
            'status' => $t->status->value,
            'is_system_template' => $t->is_system_template,
            'locale' => $t->locale,
            'version' => $t->version,
            'updated_at' => $t->updated_at?->diffForHumans(),
            'created_at' => $t->created_at?->diffForHumans(),
            'updated_by_name' => $t->updater?->name,
        ];
    }
}
