<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Card */
final class CardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'list_id' => $this->list_id,
            'board_id' => $this->board_id,
            'task_number' => $this->task_number,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position,
            'cover_color' => $this->cover_color,
            'cover_url' => $this->cover_url,
            'due_date' => $this->due_date?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'is_completed' => $this->isCompleted(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'priority' => $this->priority?->value,
            'uat_status' => $this->uat_status?->value,
            'needs_rework' => (bool) $this->needs_rework,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'assignees' => UserResource::collection($this->whenLoaded('assignees')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'comments_count' => $this->whenCounted('comments'),
            'attachments_count' => $this->whenCounted('attachments'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
