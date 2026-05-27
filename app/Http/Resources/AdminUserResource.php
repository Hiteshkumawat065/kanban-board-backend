<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Richer user payload used only on the /api/v1/admin/users surface.
 * Includes role names + per-user workspace/board assignments so the
 * "edit user" screen can render every relevant control in one round-trip.
 *
 * @mixin User
 */
final class AdminUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->gravatar(),
            'job_title' => $this->job_title?->value,
            'job_title_label' => $this->job_title?->label(),
            'mentor_id' => $this->mentor_id,

            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()
                ->pluck('name')
                ->values()
                ->all(),

            // Resource scope assignments. Eager-loaded by the controller
            // so this resource doesn't trigger N+1 queries.
            'workspaces' => $this->whenLoaded(
                'workspaces',
                fn () => $this->workspaces->map(fn ($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'role' => $w->pivot->role,
                ])->values()->all(),
                []
            ),
            'boards' => $this->whenLoaded(
                'boards',
                fn () => $this->boards->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'workspace_id' => $b->workspace_id,
                    'role' => $b->pivot->role,
                ])->values()->all(),
                []
            ),

            'created_at' => $this->created_at,
        ];
    }
}
