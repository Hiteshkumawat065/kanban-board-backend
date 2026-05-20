<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Workspace */
final class WorkspaceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'avatar_url' => $this->avatar_url,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'role' => $this->whenPivotLoaded('workspace_members', fn () => $this->pivot->role),
            'boards_count' => $this->whenCounted('boards'),
            'members_count' => $this->whenCounted('members'),
            // Gate-derived flags so the UI can show/hide Edit/Delete without
            // duplicating role logic. Reflects WorkspacePolicy exactly.
            'permissions' => [
                'update' => $user !== null && $user->can('update', $this->resource),
                'delete' => $user !== null && $user->can('delete', $this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
