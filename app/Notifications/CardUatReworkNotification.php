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
 * Sent to the assigned developer(s) when their mentor rejects UAT and
 * requests rework. The card has been moved back to the To Do list and
 * is flagged high-priority with a red REWORK badge.
 */
final class CardUatReworkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Card $card,
        public User $mentor,
        public ?string $reason = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $boardUrl = url("/boards/{$this->card->board_id}");

        $mail = (new MailMessage)
            ->error()
            ->subject("Rework requested: {$this->card->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->mentor->name} has requested rework on your task.")
            ->line("Task: **{$this->card->title}**");

        if ($this->reason !== null && $this->reason !== '') {
            $mail->line("Reason: {$this->reason}");
        }

        $mail->line('The card has been moved back to the To Do list and marked high priority.')
            ->action('Open board', $boardUrl);

        return $mail;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.uat.rework',
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'board_id' => $this->card->board_id,
            'mentor_id' => $this->mentor->id,
            'mentor_name' => $this->mentor->name,
            'reason' => $this->reason,
        ];
    }
}
