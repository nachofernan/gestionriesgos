<?php

namespace App\Services\Auditoria;

/**
 * Del cuestionario del plan al número (D-050): las reglas de validación de las
 * respuestas, cómo se guardan en `planes_accion.evaluacion_estimada` /
 * `evaluacion_final` y cuánto reducen impacto y probabilidad. Mismo molde que
 * EvaluacionRiesgo; la consumen el componente ValorPlan (al cargar) y
 * PlanAccion (al calcular), para que no puedan calcular distinto.
 *
 * La evaluación guardada es `['probabilidad' => {id: 0|1}, 'impacto' => {id: 0|1}]`,
 * con las preguntas de config/plan_preguntas.php.
 */
class EvaluacionPlan
{
    /** Puntos que reduce cada respuesta "sí" en su eje. */
    public const PUNTOS_POR_SI = 2;

    /**
     * Reglas del request: una respuesta sí/no (1/0) por pregunta de cada eje.
     * Test: la_evaluacion_del_plan_exige_las_diez_respuestas_de_si_o_no.
     */
    public static function reglas(): array
    {
        $reglas = [];
        foreach (config('plan_preguntas') as $eje => $preguntas) {
            $reglas["respuestas.$eje"] = 'required|array|size:'.count($preguntas);
            foreach ($preguntas as $p) {
                $reglas["respuestas.$eje.{$p['id']}"] = 'required|integer|in:0,1';
            }
        }

        return $reglas;
    }

    /** Arma la evaluación a guardar con lo que ya pasó por reglas(). */
    public static function desdeRespuestas(array $respuestas): array
    {
        $evaluacion = [];
        foreach (config('plan_preguntas') as $eje => $preguntas) {
            foreach ($preguntas as $p) {
                $evaluacion[$eje][$p['id']] = (int) $respuestas[$eje][$p['id']];
            }
        }

        return $evaluacion;
    }

    /**
     * Cuánto reduce cada eje una evaluación: 2 puntos por cada sí, hasta 10. Sin
     * evaluación, 0 y 0.
     * Test: el_plan_reduce_dos_puntos_por_cada_si_en_su_eje.
     *
     * @return array{impacto:int, probabilidad:int}
     */
    public static function calcular(?array $evaluacion): array
    {
        $eje = fn (string $nombre) => self::PUNTOS_POR_SI * (int) array_sum($evaluacion[$nombre] ?? []);

        return ['impacto' => $eje('impacto'), 'probabilidad' => $eje('probabilidad')];
    }

    /** Las respuestas en una línea, para el historial y las propuestas. */
    public static function resumen(?array $evaluacion): string
    {
        if (empty($evaluacion)) {
            return '—';
        }
        $r = self::calcular($evaluacion);

        return "Prob. −{$r['probabilidad']} · Imp. −{$r['impacto']}";
    }
}
