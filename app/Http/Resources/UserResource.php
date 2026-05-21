<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->gravatar(),
            'timezone' => $this->timezone,
            'mentor_id' => $this->mentor_id,
            // Functional role — used by the board listing screen to bucket
            // workspace members into Developers / Designers / QA / Manager.
            'job_title' => $this->job_title?->value,
            'job_title_label' => $this->job_title?->label(),
        ];
    }
}
