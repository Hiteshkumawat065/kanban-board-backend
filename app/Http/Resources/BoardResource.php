<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Board */
final class BoardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'description' => $this->description,
            'background_color' => $this->background_color,
            'background_url' => $this->background_url,
            'visibility' => $this->visibility?->value,
            'is_archived' => $this->isArchived(),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'lists' => BoardListResource::collection($this->whenLoaded('lists')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'members' => $this->when(
                $this->resource->relationLoaded('workspace')
                    && $this->resource->workspace->relationLoaded('members'),
                fn () => UserResource::collection($this->resource->workspace->members),
            ),
            // Gate-derived flags so the UI can show/hide Edit/Delete icons
            // without duplicating the BoardPolicy rules client-side.
            'permissions' => [
                'update' => $user !== null && $user->can('update', $this->resource),
                'delete' => $user !== null && $user->can('delete', $this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
