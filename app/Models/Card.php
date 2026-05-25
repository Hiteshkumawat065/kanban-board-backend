<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardPriority;
use App\Enums\UatStatus;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'list_id',
        'board_id',
        'task_number',
        'title',
        'description',
        'position',
        'cover_color',
        'cover_url',
        'due_date',
        'started_at',
        'completed_at',
        'created_by',
        'archived_at',
        'uat_status',
        'needs_rework',
        'priority',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * Per-board task number to start counting from.
     *
     * The first card on a brand-new board becomes #101, the second #102, etc.
     * Each board has its own independent counter.
     */
    public const TASK_NUMBER_START = 101;

    protected function casts(): array
    {
        return [
            'position' => 'float',
            'task_number' => 'integer',
            'due_date' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'uat_status' => UatStatus::class,
            'priority' => CardPriority::class,
            'needs_rework' => 'boolean',
        ];
    }

    /**
     * Auto-assign a per-board `task_number` when a card is first created.
     *
     * The next number is `max(task_number) + 1` for the card's board, or
     * `TASK_NUMBER_START` if this is the first card on the board.
     * Soft-deleted cards are included via `withTrashed()` so deleting a
     * card doesn't free its number for reuse (avoids confusing duplicates
     * in history / activity logs).
     *
     * `CardService::create` already wraps this in a DB transaction, which
     * gives us read-consistency within a single create call. We also take
     * a row lock on the parent board to serialize concurrent creates on
     * the same board and prevent two cards from grabbing the same number.
     */
    protected static function booted(): void
    {
        static::creating(function (self $card): void {
            if ($card->task_number !== null || $card->board_id === null) {
                return;
            }

            Board::whereKey($card->board_id)->lockForUpdate()->first();

            $max = (int) static::query()
                ->withTrashed()
                ->where('board_id', $card->board_id)
                ->max('task_number');

            $card->task_number = $max > 0 ? $max + 1 : self::TASK_NUMBER_START;
        });
    }

    /** @return BelongsTo<BoardList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(BoardList::class, 'list_id');
    }

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The mentor who last approved / requested rework on this card.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'card_assignees')
            ->withPivot('assigned_by', 'assigned_at');
    }

    /** @return BelongsToMany<Label, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'card_label');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    /** @return HasMany<Checklist, $this> */
    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class)->orderBy('position');
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNull('completed_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->isCompleted();
    }
}
