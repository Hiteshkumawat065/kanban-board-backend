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
 * Sent to the *mentor* of each assignee when a card is moved into a
 * different list (e.g. To Do -> In Progress). The assignee whose card
 * moved is named in the body so the mentor sees which developer made
 * the move.
 *
 * Triggered by CardService::move() for cross-list moves only — pure
 * reordering inside the same list is intentionally silent. To avoid
 * duplicate mail, the service skips this notification for transitions
 * that are already covered by the richer, stage-specific
 * CardStageChangedNotification / CardCompletedNotification.
 */
final class CardListMovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Card $card,
        public User $developer,
        public string $fromListName,
        public string $toListName,
        public ?User $actor = null,
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
            ->subject("Task moved: {$this->card->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->developer->name} moved a task to a different list.")
            ->line("Task: **{$this->card->title}**")
            ->line("From: {$this->fromListName}")
            ->line("To: {$this->toListName}")
            ->action('Open board', $boardUrl)
            ->line('— Kanban workflow');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.list_moved',
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'board_id' => $this->card->board_id,
            'developer_id' => $this->developer->id,
            'developer_name' => $this->developer->name,
            'from_list' => $this->fromListName,
            'to_list' => $this->toListName,
            'actor_id' => $this->actor?->id,
            'actor_name' => $this->actor?->name,
        ];
    }
}
