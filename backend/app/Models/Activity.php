<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Activity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'board_id',
        'card_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Convenience logger.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function log(
        ActivityAction $action,
        Model $subject,
        array $metadata = [],
        ?int $workspaceId = null,
        ?int $boardId = null,
        ?int $cardId = null,
    ): self {
        return self::create([
            'workspace_id' => $workspaceId,
            'board_id' => $boardId,
            'card_id' => $cardId,
            'user_id' => auth()->id(),
            'action' => $action->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
