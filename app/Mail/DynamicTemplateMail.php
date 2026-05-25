<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Generic mailable used by {@see App\Services\EmailTemplate\DynamicMailService}.
 *
 * All header bits (from / reply-to / cc / bcc / attachments) are passed in
 * via the constructor so a single class can deliver every template
 * regardless of category. The HTML body is the already-rendered output
 * of {@see App\Services\EmailTemplate\VariableParser} — wrapped in the
 * shared `emails.layout` blade so every email gets the branded shell.
 */
final class DynamicTemplateMail extends Mailable implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $cc
     * @param  array<int, string>  $bcc
     * @param  array<int, array{path: string, name?: ?string, mime?: ?string}>  $attachments
     */
    public function __construct(
        public string $subject,
        public string $bodyHtml,
        public ?string $bodyText = null,
        public ?string $fromName = null,
        public ?string $fromEmail = null,
        public ?string $replyTo = null,
        public array $cc = [],
        public array $bcc = [],
        public array $attachments = [],
    ) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(subject: $this->subject);

        if ($this->fromEmail) {
            $envelope = $envelope->from(new Address($this->fromEmail, $this->fromName ?? ''));
        }
        if ($this->replyTo) {
            $envelope = $envelope->replyTo([$this->replyTo]);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.layout',
            text: $this->bodyText ? 'emails.layout_text' : null,
            with: [
                'bodyHtml' => $this->bodyHtml,
                'bodyText' => $this->bodyText,
                'companyName' => (string) config('app.name', 'Kanban'),
                'currentYear' => (string) now()->year,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attached = [];
        foreach ($this->attachments as $a) {
            if (empty($a['path'])) {
                continue;
            }
            $att = Attachment::fromPath($a['path']);
            if (! empty($a['name'])) {
                $att = $att->as($a['name']);
            }
            if (! empty($a['mime'])) {
                $att = $att->withMime($a['mime']);
            }
            $attached[] = $att;
        }

        return $attached;
    }
}
