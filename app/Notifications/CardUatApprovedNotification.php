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
 * Sent to the assigned developer(s) when their mentor approves UAT.
 * The card can now be moved to a done-stage list.
 */
final class CardUatApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Card $card,
        public User $mentor,
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
            ->subject("UAT approved: {$this->card->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->mentor->name} has approved your UAT submission.")
            ->line("Task: **{$this->card->title}**")
            ->line('You can now move this card to a Done / QA Testing list.')
            ->action('Open board', $boardUrl);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.uat.approved',
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'board_id' => $this->card->board_id,
            'mentor_id' => $this->mentor->id,
            'mentor_name' => $this->mentor->name,
        ];
    }
}
