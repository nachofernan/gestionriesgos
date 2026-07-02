<?php

namespace Database\Factories\Auditoria;

use App\Models\Auditoria\TipoRiesgo;
use Illuminate\Database\Eloquent\Factories\Factory;

class TipoRiesgoFactory extends Factory
{
    protected $model = TipoRiesgo::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->word(),
            'descripcion' => $this->faker->sentence(),
        ];
    }
}