<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardList>
 */
final class BoardListFactory extends Factory
{
    protected $model = BoardList::class;

    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'name' => fake()->randomElement(['To Do', 'In Progress', 'Review', 'Done']),
            'position' => fake()->randomFloat(4, 1, 1000),
        ];
    }
}
