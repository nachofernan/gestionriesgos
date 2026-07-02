<?php

namespace Database\Factories\Auditoria;

use App\Models\Auditoria\Tarea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TareaFactory extends Factory
{
    protected $model = Tarea::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->sentence(3),
            'descripcion' => $this->faker->paragraph(),
            'fecha' => $this->faker->date(),
            'porcentaje_avance' => $this->faker->numberBetween(0, 100),
            'user_id' => User::factory(),
        ];
    }
}