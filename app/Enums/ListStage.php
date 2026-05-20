<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stable workflow-stage identifier for a list.
 *
 * Names of lists are user-editable; this enum lets us reason about them
 * by role (e.g. "where does an approved UAT card move to?") without
 * relying on the display name.
 */
enum ListStage: string
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Uat = 'uat';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Uat => 'UAT',
            self::Done => 'Done',
        };
    }
}
