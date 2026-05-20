<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class WorkspaceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $owner, array $data): Workspace
    {
        return DB::transaction(function () use ($owner, $data) {
            $workspace = Workspace::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_id' => $owner->id,
            ]);

            $workspace->members()->attach($owner->id, [
                'role' => WorkspaceRole::Owner->value,
                'joined_at' => now(),
            ]);

            Activity::log(
                ActivityAction::WorkspaceCreated,
                $workspace,
                [],
                workspaceId: $workspace->id,
            );

            return $workspace;
        });
    }

    public function invite(Workspace $workspace, User $inviter, string $email, WorkspaceRole $role): WorkspaceInvitation
    {
        return DB::transaction(function () use ($workspace, $inviter, $email, $role) {
            $invite = WorkspaceInvitation::create([
                'workspace_id' => $workspace->id,
                'email' => $email,
                'role' => $role->value,
                'invited_by' => $inviter->id,
            ]);

            Notification::route('mail', $email)
                ->notify(new WorkspaceInvitationNotification($invite));

            Activity::log(
                ActivityAction::MemberInvited,
                $invite,
                ['email' => $email, 'role' => $role->value],
                workspaceId: $workspace->id,
            );

            return $invite;
        });
    }

    public function acceptInvitation(WorkspaceInvitation $invite, User $user): void
    {
        DB::transaction(function () use ($invite, $user) {
            $invite->workspace->members()->syncWithoutDetaching([
                $user->id => [
                    'role' => $invite->role,
                    'joined_at' => now(),
                ],
            ]);

            $invite->update(['accepted_at' => now()]);

            Activity::log(
                ActivityAction::MemberJoined,
                $user,
                ['user_id' => $user->id],
                workspaceId: $invite->workspace_id,
            );
        });
    }
}
