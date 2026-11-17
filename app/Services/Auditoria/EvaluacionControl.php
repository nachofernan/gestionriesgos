<?php

namespace App\Services\Auditoria;

use Illuminate\Validation\Rule;

/**
 * Del cuestionario del control a su valor (D-051): las reglas de validación de
 * las respuestas, cómo se guardan en `controles.evaluacion`, el puntaje, lo que
 * mitiga y su clasificación. Mismo molde que EvaluacionPlan; la consumen el
 * componente ValorControl (al cargar) y Control (al calcular), para que no
 * puedan calcular distinto.
 *
 * La evaluación guardada es
 * `['respuestas' => {id: 0|1}, 'procedimiento' => ?string, 'sistemas' => list<string>,
 *   'sistema_otro' => ?string, 'frecuencia' => ?string]`, con las preguntas de
 * config/control_preguntas.php. Los detalles sólo se guardan si su respuesta es «sí».
 */
class EvaluacionControl
{
    /**
     * Reglas del request para el cuestionario que llega en $cuestionario (bajo la
     * clave 'cuestionario'): una respuesta sí/no por pregunta y, según la
     * respuesta, su detalle. Documentado pide el procedimiento; en un sistema,
     * al menos uno de la lista u «otro» con texto; con frecuencia, cuál.
     * Test: el_cuestionario_exige_las_respuestas_y_el_detalle_de_cada_si.
     */
    public static function reglas(array $cuestionario): array
    {
        $si = fn (string $id) => (int) ($cuestionario['respuestas'][$id] ?? 0) === 1;

        $reglas = [];
        foreach (config('control_preguntas.preguntas') as $p) {
            $reglas["cuestionario.respuestas.{$p['id']}"] = 'required|integer|in:0,1';
        }

        $sistemaOtro = trim((string) ($cuestionario['sistema_otro'] ?? ''));

        return $reglas + [
            'cuestionario.procedimiento' => [Rule::requiredIf($si('documentado')), 'nullable', 'string', 'max:255'],
            'cuestionario.sistemas' => [Rule::requiredIf($si('sistema') && $sistemaOtro === ''), 'nullable', 'array'],
            'cuestionario.sistemas.*' => ['string', Rule::in(config('control_preguntas.sistemas'))],
            'cuestionario.sistema_otro' => ['nullable', 'string', 'max:255'],
            'cuestionario.frecuencia' => [Rule::requiredIf($si('frecuencia')), 'nullable', Rule::in(array_keys(config('control_preguntas.frecuencias')))],
        ];
    }

    public static function mensajes(): array
    {
        return [
            'cuestionario.respuestas.*.required' => 'Respondé todas las preguntas.',
            'cuestionario.procedimiento.required' => 'Indicá en qué procedimiento, instructivo o anexo está documentado.',
            'cuestionario.sistemas.required' => 'Marcá en qué sistema ocurre, o escribilo en «Otro».',
            'cuestionario.frecuencia.required' => 'Elegí con qué frecuencia ocurre.',
        ];
    }

    /** Arma la evaluación a guardar con lo que ya pasó por reglas(): los detalles de un «no» se descartan. */
    public static function desdeRespuestas(array $cuestionario): array
    {
        $respuestas = [];
        foreach (config('control_preguntas.preguntas') as $p) {
            $respuestas[$p['id']] = (int) $cuestionario['respuestas'][$p['id']];
        }

        $texto = fn (?string $valor) => ($valor = trim((string) $valor)) === '' ? null : $valor;
        $enSistema = $respuestas['sistema'] === 1;

        return [
            'respuestas' => $respuestas,
            'procedimiento' => $respuestas['documentado'] === 1 ? $texto($cuestionario['procedimiento'] ?? null) : null,
            'sistemas' => $enSistema ? array_values(array_intersect(config('control_preguntas.sistemas'), $cuestionario['sistemas'] ?? [])) : [],
            'sistema_otro' => $enSistema ? $texto($cuestionario['sistema_otro'] ?? null) : null,
            'frecuencia' => $respuestas['frecuencia'] === 1 ? ($cuestionario['frecuencia'] ?? null) : null,
        ];
    }

    /**
     * Puntaje del cuestionario: la suma de lo que vale cada respuesta (de 1 a 10).
     * Test: el_puntaje_suma_lo_que_vale_cada_respuesta.
     */
    public static function puntaje(array $evaluacion): int
    {
        $puntaje = 0;
        foreach (config('control_preguntas.preguntas') as $p) {
            $puntaje += (int) ($evaluacion['respuestas'][$p['id']] ?? 0) === 1 ? $p['si'] : $p['no'];
        }

        return $puntaje;
    }

    /** Si el control existe y opera hoy (pregunta 1). Uno que es expresión de deseo no mitiga. */
    public static function operativo(array $evaluacion): bool
    {
        return (int) ($evaluacion['respuestas']['operativo'] ?? 0) === 1;
    }

    /**
     * Lo que mitiga el control según su cuestionario: el puntaje, o 0 si es una
     * expresión de deseo. Es el mitigacion_default que guarda Control al salvar.
     * Test: un_control_expresion_de_deseo_muestra_puntaje_y_mitiga_cero.
     */
    public static function mitigacion(array $evaluacion): int
    {
        return self::operativo($evaluacion) ? self::puntaje($evaluacion) : 0;
    }

    /**
     * Clasificación cualitativa de un puntaje: < 4 deficiente (rojo), 4–6
     * moderado (amarillo), 7 o más suficiente (verde). Un control que no opera
     * es deficiente aunque sume.
     * Test: la_clasificacion_del_control_sigue_sus_umbrales.
     *
     * @return array{etiqueta:string, color:string}
     */
    public static function clasificacion(int $puntaje, bool $operativo = true): array
    {
        return match (true) {
            ! $operativo, $puntaje < 4 => ['etiqueta' => 'deficiente', 'color' => 'rojo'],
            $puntaje < 7 => ['etiqueta' => 'moderado', 'color' => 'amarillo'],
            default => ['etiqueta' => 'suficiente', 'color' => 'verde'],
        };
    }

    /** Las respuestas en una línea, para el historial y las propuestas. */
    public static function resumen(?array $evaluacion): string
    {
        if (empty($evaluacion['respuestas'])) {
            return '—';
        }

        $puntaje = self::puntaje($evaluacion);
        $operativo = self::operativo($evaluacion);
        $texto = "Puntaje {$puntaje}/10 · ".self::clasificacion($puntaje, $operativo)['etiqueta'];

        return $operativo ? $texto : $texto.' (expresión de deseo, mitiga 0)';
    }
}
