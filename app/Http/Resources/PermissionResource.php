<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Permission as PermEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/** @mixin Permission */
final class PermissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Enrich the raw row with the human label + group from our enum
        // catalog so the admin UI doesn't have to maintain a duplicate
        // mapping. Permissions created at runtime (not in the enum) fall
        // back to a humanized version of their slug.
        $enum = PermEnum::tryFrom((string) $this->name);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $enum?->label() ?? $this->humanize($this->name),
            'group' => $enum?->group() ?? 'Custom',
            'guard_name' => $this->guard_name,
        ];
    }

    private function humanize(string $key): string
    {
        return ucwords(str_replace(['.', '_'], ' ', $key));
    }
}
