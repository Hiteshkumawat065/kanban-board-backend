<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CardCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Card $card) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("board.{$this->card->board_id}")];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'card' => (new CardResource($this->card))->resolve(),
        ];
    }
}
