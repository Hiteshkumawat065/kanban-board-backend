<?php

declare(strict_types=1);

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('board.{boardId}', function (User $user, int $boardId): bool {
    $board = Board::find($boardId);

    return $board !== null && $board->workspace->hasMember($user);
});
