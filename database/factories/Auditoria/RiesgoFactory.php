<?php

namespace Database\Factories\Auditoria;

use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\Auditoria\Estado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiesgoFactory extends Factory
{
    protected $model = Riesgo::class;

    public function definition(): array
    {
        return [
            'codigo'           => 'R-' . $this->faker->unique()->numerify('####'),
            'nombre'           => $this->faker->sentence(3),
            'descripcion'      => $this->faker->paragraph(),
            'impacto'          => $this->faker->numberBetween(0, 10),
            'probabilidad'     => $this->faker->numberBetween(0, 10),
            'mayor_criticidad' => false,
            'tipo_riesgo_id'   => TipoRiesgo::factory(),
            'user_id'          => User::factory(),
            // estado_id no se fija aquí — el boot() del modelo asigna borrador automáticamente
        ];
    }

    public function aprobado(): static
    {
        return $this->state(['estado_id' => Estado::firstOrCreate(['nombre' => 'aprobado'], ['color' => 'green'])->id]);
    }

    public function validado(): static
    {
        return $this->state(['estado_id' => Estado::firstOrCreate(['nombre' => 'validado'], ['color' => 'blue'])->id]);
    }

    public function borrador(): static
    {
        return $this->state(['estado_id' => Estado::firstOrCreate(['nombre' => 'borrador'], ['color' => 'gray'])->id]);
    }
}
