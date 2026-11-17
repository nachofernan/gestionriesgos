<?php

namespace Database\Factories\Auditoria;

use App\Models\Auditoria\Control;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto el control no tiene cuestionario y lleva un número suelto, como
 * los controles de antes de D-051: así un test fija lo que mitiga con
 * `mitigacion_default`. Para validarlo o aprobarlo hace falta `valorado()`.
 */
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

    /** Control con el cuestionario contestado para que sume $puntaje (2-10 si opera). */
    public function valorado(int $puntaje = 6, bool $operativo = true): static
    {
        return $this->state(['evaluacion' => self::evaluacion($puntaje, $operativo)]);
    }

    /**
     * Respuestas del cuestionario (config/control_preguntas.php) que suman
     * $puntaje: parte de operativo + manual (2) y va sumando «sí». Con
     * $operativo en false, la primera respuesta es «no»: suma igual, pero mitiga 0.
     */
    public static function evaluacion(int $puntaje, bool $operativo = true): array
    {
        $respuestas = ['operativo' => (int) $operativo, 'documentado' => 0, 'sistema' => 0, 'responsable' => 0, 'frecuencia' => 0, 'evidencia' => 0, 'detecta' => 0];
        $resto = $puntaje - (int) $operativo - 1;
        foreach (['documentado' => 2, 'sistema' => 2, 'responsable' => 1, 'frecuencia' => 1, 'evidencia' => 1, 'detecta' => 1] as $id => $suma) {
            if ($suma <= $resto) {
                $respuestas[$id] = 1;
                $resto -= $suma;
            }
        }

        return [
            'respuestas' => $respuestas,
            'procedimiento' => $respuestas['documentado'] ? 'Procedimiento de prueba' : null,
            'sistemas' => $respuestas['sistema'] ? ['SAP'] : [],
            'sistema_otro' => null,
            'frecuencia' => $respuestas['frecuencia'] ? 'mensual' : null,
        ];
    }
}
