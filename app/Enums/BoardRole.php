<?php

declare(strict_types=1);

namespace App\Enums;

enum BoardRole: string
{
    case Admin = 'admin';
    case Member = 'member';
    case Observer = 'observer';

    public function canEdit(): bool
    {
        return in_array($this, [self::Admin, self::Member], true);
    }

    public function canManage(): bool
    {
        return $this === self::Admin;
    }
}
