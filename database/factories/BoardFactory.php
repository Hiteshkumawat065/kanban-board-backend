<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BoardVisibility;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Board>
 */
final class BoardFactory extends Factory
{
    protected $model = Board::class;

    public function definition(): array
    {
        $colors = ['#0079bf', '#d29034', '#519839', '#b04632', '#89609e', '#cd5a91'];

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->catchPhrase(),
            'description' => fake()->sentence(),
            'background_color' => fake()->randomElement($colors),
            'visibility' => BoardVisibility::Workspace,
            'created_by' => User::factory(),
        ];
    }
}
