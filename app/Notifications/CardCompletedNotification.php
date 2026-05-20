<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the mentor after the developer moves an approved card from
 * UAT into a `done`-stage list (i.e. the work is shipped).
 */
final class CardCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Card $card,
        public User $developer,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $boardUrl = url("/boards/{$this->card->board_id}");

        return (new MailMessage)
            ->subject("Task completed: {$this->card->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->developer->name} has marked the task as done after UAT approval.")
            ->line("Task: **{$this->card->title}**")
            ->action('Open board', $boardUrl);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.completed',
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'board_id' => $this->card->board_id,
            'developer_id' => $this->developer->id,
            'developer_name' => $this->developer->name,
        ];
    }
}
