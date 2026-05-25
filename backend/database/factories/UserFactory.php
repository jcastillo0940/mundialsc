<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'cedula' => fake()->unique()->numerify('########'),
            'document_type' => 'cedula',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('6###-####'),
            'email_verified_at' => now(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'role' => 'client',
            'is_active' => true,
            'birthdate' => now()->subYears(25)->toDateString(),
            'resides_in_panama' => true,
            'is_employee' => false,
            'accepted_terms_at' => now(),
            'registration_completed_at' => now(),
            'group_stage_goal_prediction' => fake()->numberBetween(50, 180),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
