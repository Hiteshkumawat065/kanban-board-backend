<?php

declare(strict_types=1);

namespace App\Services\EmailTemplate;

use App\Enums\EmailTemplateStatus;
use App\Mail\DynamicTemplateMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reusable email dispatcher that renders an EmailTemplate against a
 * runtime context and ships it through Laravel's Mail facade.
 *
 *     app(DynamicMailService::class)->send(
 *         'welcome.user',
 *         'jane@example.com',
 *         ['user_name' => 'Jane'],
 *     );
 *
 * The service queues by default (toggle via the second-to-last argument).
 * Every attempt is written to `email_logs` with status `queued`, `sent`
 * or `failed` so the admin UI can show full delivery history.
 */
final class DynamicMailService
{
    public function __construct(
        private readonly VariableParser $parser,
    ) {}

    /**
     * @param  EmailTemplate|string  $template  Either a model instance or a
     *                                          `template_key` to resolve.
     * @param  array<string, mixed>  $context  Variable values used to render
     *                                         the body / subject.
     * @param  array<string, mixed>  $options  Optional overrides:
     *                                         - `cc`, `bcc`         arrays of recipient emails
     *                                         - `reply_to`          string
     *                                         - `from_name`         string
     *                                         - `from_email`        string
     *                                         - `to_name`           string
     *                                         - `attachments`       [['path' => ..., 'name' => ...], ...]
     *                                         - `queue`             bool, defaults true
     *                                         - `bypass_inactive`   bool — when true, still sends even if the
     *                                         template is `inactive` (used by the
     *                                         "Send test" admin action).
     */
    public function send(EmailTemplate|string $template, string $to, array $context = [], array $options = []): EmailLog
    {
        $template = $template instanceof EmailTemplate
            ? $template
            : EmailTemplate::query()->ofKey($template)->firstOrFail();

        if (
            $template->status !== EmailTemplateStatus::Active
            && ! ($options['bypass_inactive'] ?? false)
        ) {
            return $this->logSkipped($template, $to, $context, 'Template is not active');
        }

        $rendered = $this->parser->renderTemplate($template, $context);

        $fromName = $options['from_name'] ?? $template->email_from_name ?? config('mail.from.name');
        $fromEmail = $options['from_email'] ?? $template->email_from_email ?? config('mail.from.address');
        $replyTo = $options['reply_to'] ?? $template->reply_to;
        $cc = $options['cc'] ?? $template->cc ?? [];
        $bcc = $options['bcc'] ?? $template->bcc ?? [];
        $attachments = $options['attachments'] ?? $template->attachments ?? [];
        $useQueue = $options['queue'] ?? true;
        $toName = $options['to_name'] ?? null;

        // Persist the log BEFORE sending so we don't lose the audit trail
        // if the SMTP transport throws synchronously.
        $log = EmailLog::create([
            'email_template_id' => $template->id,
            'template_key' => $template->template_key,
            'template_name' => $template->template_name,
            'to_email' => $to,
            'to_name' => $toName,
            'cc' => $cc,
            'bcc' => $bcc,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to' => $replyTo,
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'status' => 'queued',
            'open_tracking_id' => (string) Str::uuid(),
            'context' => $context,
            'sent_by' => Auth::id(),
        ]);

        try {
            $mailable = new DynamicTemplateMail(
                subject: $rendered['subject'],
                bodyHtml: $rendered['body'],
                bodyText: $rendered['plain_text'],
                fromName: $fromName,
                fromEmail: $fromEmail,
                replyTo: $replyTo,
                cc: $cc,
                bcc: $bcc,
                attachments: $attachments,
            );

            $pendingMail = Mail::to($to);
            if (! empty($cc)) {
                $pendingMail->cc($cc);
            }
            if (! empty($bcc)) {
                $pendingMail->bcc($bcc);
            }

            if ($useQueue) {
                $pendingMail->queue($mailable);
                // For queued sends, status stays `queued`. A future "delivered"
                // webhook (e.g. from Resend) would flip this to `sent`.
            } else {
                $pendingMail->send($mailable);
                $log->update(['status' => 'sent', 'sent_at' => now()]);
            }
        } catch (Throwable $e) {
            Log::error('DynamicMailService: send failed', [
                'template_key' => $template->template_key,
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 1000),
            ]);
        }

        return $log->refresh();
    }

    /**
     * "Send test email" admin action. Bypasses the inactive guard and
     * forces a synchronous (non-queued) dispatch so the operator gets
     * an immediate success/failure response in the UI.
     *
     * @param  array<string, mixed>  $context
     */
    public function sendTest(EmailTemplate $template, string $to, array $context = [], ?User $sender = null): EmailLog
    {
        return $this->send(
            $template,
            $to,
            $context,
            [
                'queue' => false,
                'bypass_inactive' => true,
                'to_name' => $sender?->name,
            ],
        );
    }

    private function logSkipped(EmailTemplate $template, string $to, array $context, string $reason): EmailLog
    {
        return EmailLog::create([
            'email_template_id' => $template->id,
            'template_key' => $template->template_key,
            'template_name' => $template->template_name,
            'to_email' => $to,
            'subject' => $template->subject,
            'status' => 'failed',
            'error' => $reason,
            'context' => $context,
            'sent_by' => Auth::id(),
        ]);
    }
}
