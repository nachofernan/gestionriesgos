<?php

namespace Database\Factories\Auditoria;

use App\Models\Auditoria\Control;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ControlFactory extends Factory
{
    protected $model = Control::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->sentence(3),
            'descripcion' => $this->faker->paragraph(),
            'mitigacion_default' => $this->faker->numberBetween(1, 10),
            'user_id' => User::factory(),
        ];
    }
}