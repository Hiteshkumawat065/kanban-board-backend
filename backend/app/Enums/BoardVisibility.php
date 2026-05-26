<?php

declare(strict_types=1);

namespace App\Enums;

enum BoardVisibility: string
{
    case Private = 'private';
    case Workspace = 'workspace';
    case Public = 'public';
}
