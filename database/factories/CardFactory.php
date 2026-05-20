<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
final class CardFactory extends Factory
{
    protected $model = Card::class;

    public function definition(): array
    {
        return [
            'list_id' => BoardList::factory(),
            'board_id' => Board::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'position' => fake()->randomFloat(4, 1, 1000),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }
}
