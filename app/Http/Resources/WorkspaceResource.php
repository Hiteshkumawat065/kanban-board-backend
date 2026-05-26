<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\JobTitle;
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
            // Team composition — only emitted when the `members` relation is
            // eager-loaded (the board listing screen does this). Exposes:
            //   - `members`      → flat list of UserResource (with job_title)
            //   - `team_counts`  → {developer: N, designer: N, qa: N, …}
            'members' => $this->whenLoaded('members', fn () => UserResource::collection($this->resource->members)),
            'team_counts' => $this->whenLoaded(
                'members',
                fn () => $this->computeTeamCounts(),
            ),
            // Gate-derived flags so the UI can show/hide Edit/Delete without
            // duplicating role logic. Reflects WorkspacePolicy exactly.
            'permissions' => [
                'update' => $user !== null && $user->can('update', $this->resource),
                'delete' => $user !== null && $user->can('delete', $this->resource),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Count members of this workspace grouped by their `job_title`. Returns
     * every JobTitle case so the UI can iterate predictably (even zeros).
     *
     * @return array<string, int>
     */
    private function computeTeamCounts(): array
    {
        $buckets = array_fill_keys(
            array_map(fn (JobTitle $j) => $j->value, JobTitle::cases()),
            0,
        );

        foreach ($this->resource->members as $member) {
            $key = $member->job_title?->value ?? JobTitle::Other->value;
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
        }

        return $buckets;
    }
}
