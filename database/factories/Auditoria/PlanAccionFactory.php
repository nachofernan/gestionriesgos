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
            'nombre' => $this->faker->sentence(3),
            'descripcion' => $this->faker->paragraph(),
            'user_id' => User::factory(),
            // Estimado con todo en "no": se puede validar y no mitiga nada (D-050).
            'evaluacion_estimada' => self::evaluacion(0, 0),
        ];
    }

    /** Plan todavía sin estimar: no se valida ni mitiga (D-050). */
    public function sinValorar(): static
    {
        return $this->state(['evaluacion_estimada' => null]);
    }

    /** Plan con la estimación que reduce $impacto y $probabilidad (pares, 0-10, D-050). */
    public function valorado(int $impacto, int $probabilidad): static
    {
        return $this->state(['evaluacion_estimada' => self::evaluacion($impacto, $probabilidad)]);
    }

    /**
     * Respuestas del cuestionario del plan que dan esa reducción por eje: los
     * primeros N/2 "sí" de cada eje (config/plan_preguntas.php).
     *
     * @return array{probabilidad: array<int,int>, impacto: array<int,int>}
     */
    public static function evaluacion(int $impacto, int $probabilidad): array
    {
        $eje = fn (string $nombre, int $reduccion) => collect(config("plan_preguntas.$nombre"))
            ->mapWithKeys(fn ($p, $i) => [$p['id'] => $i < intdiv($reduccion, 2) ? 1 : 0])
            ->all();

        return ['probabilidad' => $eje('probabilidad', $probabilidad), 'impacto' => $eje('impacto', $impacto)];
    }
}
