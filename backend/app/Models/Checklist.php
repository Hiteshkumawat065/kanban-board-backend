<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Checklist extends Model
{
    protected $fillable = ['card_id', 'title', 'position'];

    protected function casts(): array
    {
        return ['position' => 'float'];
    }

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** @return HasMany<ChecklistItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position');
    }

    public function progressPercent(): int
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return 0;
        }

        $done = $items->where('is_completed', true)->count();

        return (int) round(($done / $items->count()) * 100);
    }
}
