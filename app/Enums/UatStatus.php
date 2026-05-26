<?php

declare(strict_types=1);

namespace App\Enums;

enum UatStatus: string
{
    /** Card just entered UAT — mentor still needs to review. */
    case Pending = 'pending';

    /** Mentor has signed off — card is eligible to be moved to a done-stage list. */
    case Approved = 'approved';

    /** Mentor rejected — card was bounced back to the todo-stage list. */
    case Rework = 'rework';
}
