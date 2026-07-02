<?php

namespace Database\Factories\Auditoria;

use App\Models\Auditoria\PlanAccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanAccionFactory extends Factory
{
    protected $model = PlanAccion::class;

    public function definition(): array
    {
        return [
            'codigo' => 'PA-' . $this->faker->unique()->numerify('####'),
            'nombre' => $this->faker->sentence(3),
            'descripcion' => $this->faker->paragraph(),
            'user_id' => User::factory(),
        ];
    }
}