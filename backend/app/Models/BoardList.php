<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListStage;
use Database\Factories\BoardListFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Named `BoardList` to avoid PHP's reserved `List` keyword.
 * Maps to the `lists` table.
 */
final class BoardList extends Model
{
    /** @use HasFactory<BoardListFactory> */
    use HasFactory;

    protected $table = 'lists';

    protected $fillable = [
        'board_id',
        'name',
        'color',
        'stage',
        'position',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'float',
            'archived_at' => 'datetime',
            'stage' => ListStage::class,
        ];
    }

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'list_id')->orderBy('position');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
