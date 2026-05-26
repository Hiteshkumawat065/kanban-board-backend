<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Label extends Model
{
    protected $fillable = ['board_id', 'name', 'color'];

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /** @return BelongsToMany<Card, $this> */
    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class, 'card_label');
    }
}
