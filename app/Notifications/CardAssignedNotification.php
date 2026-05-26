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
 * Sent to a user when they are newly assigned to a card. Lets the
 * assignee know they own the task so they can pick it up from their
 * own dashboard / board view.
 *
 * Triggered by CardService::assign(). The notification is intentionally
 * idempotent — CardService::assign() only emits this via the syncing
 * code-path when a fresh assignment happens, so re-assigning the same
 * user won't double-mail (see CardService for the guarded dispatch).
 */
final class CardAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Card $card,
        public User $actor,
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
            ->subject("You've been assigned: {$this->card->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->actor->name} assigned you a new task.")
            ->line("Task: **{$this->card->title}**")
            ->when(
                (bool) $this->card->description,
                fn (MailMessage $m) => $m->line($this->card->description),
            )
            ->action('Open board', $boardUrl)
            ->line('— Kanban workflow');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.assigned',
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'board_id' => $this->card->board_id,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
        ];
    }
}
