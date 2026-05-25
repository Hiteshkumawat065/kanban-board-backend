<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Functional job titles used to bucket workspace members by what they do
 * (Developer / Designer / QA / Manager). Distinct from WorkspaceRole, which
 * tracks permissions (Owner / Admin / Member) and is per-workspace.
 */
enum JobTitle: string
{
    case Developer = 'developer';
    case Designer = 'designer';
    case Qa = 'qa';
    case Manager = 'manager';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Developer => 'Developer',
            self::Designer => 'Designer',
            self::Qa => 'QA',
            self::Manager => 'Manager',
            self::Other => 'Other',
        };
    }

    /**
     * Plural label used in counters / section headings on the UI.
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Developer => 'Developers',
            self::Designer => 'Designers',
            self::Qa => 'QAs',
            self::Manager => 'Managers',
            self::Other => 'Other',
        };
    }
}
