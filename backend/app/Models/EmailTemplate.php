<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailTemplateCategory;
use App\Enums\EmailTemplateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $template_name
 * @property string $template_key
 * @property string $slug
 * @property string $subject
 * @property EmailTemplateCategory $category
 * @property ?string $description
 * @property ?string $email_from_name
 * @property ?string $email_from_email
 * @property ?string $reply_to
 * @property ?array $cc
 * @property ?array $bcc
 * @property ?array $variables
 * @property string $body_content
 * @property ?string $plain_text
 * @property ?array $attachments
 * @property EmailTemplateStatus $status
 * @property bool $is_system_template
 * @property string $locale
 * @property int $version
 * @property ?int $created_by
 * @property ?int $updated_by
 */
final class EmailTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'template_name',
        'template_key',
        'slug',
        'subject',
        'category',
        'description',
        'email_from_name',
        'email_from_email',
        'reply_to',
        'cc',
        'bcc',
        'variables',
        'body_content',
        'plain_text',
        'attachments',
        'status',
        'is_system_template',
        'locale',
        'version',
        'created_by',
        'updated_by',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<EmailLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'template';
        $slug = $base;
        $i = 1;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public static function generateUniqueKey(string $name): string
    {
        $base = (string) Str::of($name)->slug('_')->snake();
        $base = $base !== '' ? $base : 'template';
        $key = $base;
        $i = 1;

        while (self::query()->where('template_key', $key)->exists()) {
            $key = "{$base}_{$i}";
            $i++;
        }

        return $key;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EmailTemplateStatus::Active->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfKey(Builder $query, string $key): Builder
    {
        return $query->where('template_key', $key);
    }

    protected function casts(): array
    {
        return [
            'cc' => 'array',
            'bcc' => 'array',
            'variables' => 'array',
            'attachments' => 'array',
            'is_system_template' => 'boolean',
            'version' => 'integer',
            'status' => EmailTemplateStatus::class,
            'category' => EmailTemplateCategory::class,
        ];
    }

    protected static function booted(): void
    {
        self::creating(function (self $template): void {
            if (empty($template->slug)) {
                $template->slug = self::generateUniqueSlug($template->template_name);
            }
            if (empty($template->template_key)) {
                $template->template_key = self::generateUniqueKey($template->template_name);
            }
        });

        self::updating(function (self $template): void {
            if ($template->isDirty(['body_content', 'subject'])) {
                $template->version = ($template->version ?? 1) + 1;
            }
        });
    }
}
