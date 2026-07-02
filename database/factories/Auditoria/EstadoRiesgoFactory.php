<?php

namespace Database\Factories\Auditoria;

use App\Models\Auditoria\EstadoRiesgo;
use Illuminate\Database\Eloquent\Factories\Factory;

class EstadoRiesgoFactory extends Factory
{
    protected $model = EstadoRiesgo::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->unique()->word(),
            'color' => $this->faker->safeColorName(),
        ];
    }
}