<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * High-level grouping for email templates shown in the admin UI.
 *
 * The category drives:
 *   - the colored badge on the templates list
 *   - the "Category" filter on the listing page
 *   - default labels in the seeder
 */
enum EmailTemplateCategory: string
{
    case Authentication = 'authentication';
    case Onboarding = 'onboarding';
    case Workspace = 'workspace';
    case Board = 'board';
    case Task = 'task';
    case Notification = 'notification';
    case Marketing = 'marketing';
    case System = 'system';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Authentication => 'Authentication',
            self::Onboarding => 'Onboarding',
            self::Workspace => 'Workspace',
            self::Board => 'Board',
            self::Task => 'Task',
            self::Notification => 'Notification',
            self::Marketing => 'Marketing',
            self::System => 'System',
            self::Other => 'Other',
        };
    }

    /**
     * Tailwind-friendly badge tint used by the list & cards UI. Centralised
     * here so every consumer (Vue + Blade) stays in sync.
     */
    public function color(): string
    {
        return match ($this) {
            self::Authentication => 'indigo',
            self::Onboarding => 'emerald',
            self::Workspace => 'sky',
            self::Board => 'cyan',
            self::Task => 'amber',
            self::Notification => 'violet',
            self::Marketing => 'pink',
            self::System => 'slate',
            self::Other => 'gray',
        };
    }

    /** @return array<int, array{value: string, label: string, color: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'color' => $c->color(),
            ],
            self::cases(),
        );
    }
}
