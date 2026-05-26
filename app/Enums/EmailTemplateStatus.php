<?php

declare(strict_types=1);

namespace App\Enums;

enum EmailTemplateStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Draft => 'Draft',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Inactive => 'rose',
            self::Draft => 'amber',
        };
    }

    /** @return array<int, array{value: string, label: string, color: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ],
            self::cases(),
        );
    }
}
