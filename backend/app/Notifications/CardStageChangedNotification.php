<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ListStage;
use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mentor-facing notification fired on every workflow transition the
 * developer makes (Backlog -> To Do, To Do -> In Progress, In Progress -> UAT).
 *
 * Subject + body adapt to the new stage. Stored on the `database` channel
 * too so the mentor sees an in-app inbox alongside email.
 */
final class CardStageChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Card $card,
        public User $developer,
        public ListStage $fromStage,
        public ListStage $toStage,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $boardUrl = url("/boards/{$this->card->board_id}");

        $line = match ($this->toStage) {
            ListStage::Todo => "{$this->developer->name} has picked up the task and moved it to the To Do list.",
            ListStage::InProgress => "{$this->developer->name} has started working on the task — it is now In Progress.",
            ListStage::Uat => "{$this->developer->name} has submitted the task for UAT review. Please review and approve or request rework.",
            default => "{$this->developer->name} moved the task from {$this->fromStage->label()} to {$this->toStage->label()}.",
        };

        $subject = match ($this->toStage) {
            ListStage::Uat => "UAT review needed: {$this->card->title}",
            default => "[{$this->toStage->label()}] {$this->card->title}",
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($line)
            ->line("Task: **{$this->card->title}**")
            ->action('Open board', $boardUrl)
            ->line('— Kanban workflow');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.stage_changed',
            'card_id' => $this->card->id,
            'card_title' => $this->card->title,
            'board_id' => $this->card->board_id,
            'developer_id' => $this->developer->id,
            'developer_name' => $this->developer->name,
            'from_stage' => $this->fromStage->value,
            'to_stage' => $this->toStage->value,
        ];
    }
}
