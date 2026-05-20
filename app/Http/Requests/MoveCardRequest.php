<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MoveCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string|int>> */
    public function rules(): array
    {
        return [
            'list_id' => ['required', 'integer', 'exists:lists,id'],
            'position' => ['required', 'integer', 'min:0'],
        ];
    }
}
