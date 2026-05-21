<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        // We use yopmail.com for every seeded / factory-created user so
        // every notification the app sends lands in a disposable mailbox
        // that anyone on the team can open at https://yopmail.com.
        $localPart = Str::slug(fake()->unique()->userName(), '.');

        return [
            'name' => fake()->name(),
            'email' => $localPart.'@yopmail.com',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'timezone' => 'UTC',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
