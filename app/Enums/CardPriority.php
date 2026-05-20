<?php

declare(strict_types=1);

namespace App\Enums;

enum CardPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-100 text-slate-700',
            self::Medium => 'bg-blue-100 text-blue-700',
            self::High => 'bg-rose-100 text-rose-700',
        };
    }
}
