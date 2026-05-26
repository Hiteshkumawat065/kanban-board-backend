<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BoardList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BoardList */
final class BoardListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'board_id' => $this->board_id,
            'name' => $this->name,
            'color' => $this->color,
            'stage' => $this->stage?->value,
            'position' => $this->position,
            'cards' => CardResource::collection($this->whenLoaded('cards')),
        ];
    }
}
