<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Roles de spatie (D-052). Se pueden encadenar: ->gerente()->comite() deja
     * al usuario con los dos roles.
     */
    public function conRol(string $rol): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::findOrCreate($rol, 'web')));
    }

    public function gerente(): static
    {
        return $this->conRol('gerente');
    }

    public function comite(): static
    {
        return $this->conRol('comite');
    }

    public function empleado(): static
    {
        return $this->conRol('empleado');
    }

    public function auditor(): static
    {
        return $this->conRol('auditor');
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
