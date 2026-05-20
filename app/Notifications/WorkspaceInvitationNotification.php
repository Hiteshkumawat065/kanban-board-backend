<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkspaceInvitation $invitation) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/invitations/{$this->invitation->token}");
        $workspace = $this->invitation->workspace->name;
        $inviter = $this->invitation->inviter->name;

        return (new MailMessage)
            ->subject("You're invited to join {$workspace} on Kanban")
            ->greeting('Hello!')
            ->line("{$inviter} has invited you to join the workspace \"{$workspace}\".")
            ->action('Accept Invitation', $url)
            ->line('This invitation expires in 7 days.');
    }
}
