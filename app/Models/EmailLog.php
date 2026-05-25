<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted record of a single email dispatch attempt. Written by
 * {@see App\Services\EmailTemplate\DynamicMailService} regardless of the
 * eventual delivery outcome so the admin panel can show full history.
 */
final class EmailLog extends Model
{
    protected $fillable = [
        'email_template_id',
        'template_key',
        'template_name',
        'to_email',
        'to_name',
        'cc',
        'bcc',
        'from_email',
        'from_name',
        'reply_to',
        'subject',
        'body',
        'status',
        'error',
        'sent_at',
        'opened_at',
        'open_tracking_id',
        'context',
        'sent_by',
    ];

    /** @return BelongsTo<EmailTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    protected function casts(): array
    {
        return [
            'cc' => 'array',
            'bcc' => 'array',
            'context' => 'array',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
        ];
    }
}
