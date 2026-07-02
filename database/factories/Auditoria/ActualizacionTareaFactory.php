<?php

namespace Database\Factories\Auditoria;

use App\Models\Auditoria\Actualizacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActualizacionTareaFactory extends Factory
{
    protected $model = Actualizacion::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mensaje' => $this->faker->sentence(),
            'data' => null,
        ];
    }
}
