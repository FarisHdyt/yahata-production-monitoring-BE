<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nik' => null,
            'name' => fake()->name(),
            'username' => 'user' . fake()->unique()->numerify('#####'),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Password123', // di-hash otomatis oleh cast "hashed"
            'role' => 'operator',
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}