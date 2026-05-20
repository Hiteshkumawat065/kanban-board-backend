<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Calculates a fractional position for drag-and-drop reordering
 * using the midpoint algorithm. O(1) writes per move.
 *
 * @see docs/SCHEMA.md#position-strategy
 */
final class PositionCalculator
{
    /**
     * Compute new position when inserting between two siblings.
     *
     * @param  Collection<int, Model>  $siblings  ordered by position ASC
     * @param  int  $targetIndex  zero-based destination index
     */
    public static function between(Collection $siblings, int $targetIndex): float
    {
        $count = $siblings->count();

        if ($count === 0) {
            return 1.0;
        }

        if ($targetIndex <= 0) {
            return (float) $siblings->first()->position - 1.0;
        }

        if ($targetIndex >= $count) {
            return (float) $siblings->last()->position + 1.0;
        }

        $prev = (float) $siblings->get($targetIndex - 1)->position;
        $next = (float) $siblings->get($targetIndex)->position;

        return ($prev + $next) / 2.0;
    }

    public static function append(Collection $siblings): float
    {
        if ($siblings->isEmpty()) {
            return 1.0;
        }

        return (float) $siblings->last()->position + 1.0;
    }
}
