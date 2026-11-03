<?php

namespace App\Services\Auditoria;

use App\Enums\Auditoria\Condicion;
use App\Enums\Auditoria\MetodoEvaluacion;
use App\Enums\Auditoria\Responsabilidad;
use App\Enums\Auditoria\Temporalidad;
use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Del wizard al número: las reglas de validación de las respuestas, cómo se
 * guardan en `riesgos.evaluacion` y cómo dan impacto y probabilidad, según el
 * método del riesgo (D-042, D-043). La consumen RiesgoController::store() y
 * recalcularStore(), para que el alta y el recálculo no puedan calcular distinto.
 *
 * La evaluación guardada es `['metodo' => ..., 'respuestas' => [...]]`.
 * Clásica: respuestas = {probabilidad: {id: 0-2}, impacto: {id: 0-2}}.
 * Ambiental: respuestas = {control, frecuencia, medio, naturaleza, magnitud,
 * materialidad_impacto: [ids], materialidad_financiera: [ids]}.
 */
class EvaluacionRiesgo
{
    /** Ejes ambientales con una sola elección, y dónde están sus opciones en la config. */
    public const EJES_AMBIENTALES = [
        'control' => 'probabilidad.control',
        'frecuencia' => 'probabilidad.frecuencia',
        'medio' => 'impacto.medio',
        'naturaleza' => 'impacto.naturaleza',
        'magnitud' => 'impacto.magnitud',
    ];

    /** Reglas del request (mismos nombres de campo que el form del wizard). */
    public static function reglas(MetodoEvaluacion $metodo): array
    {
        if ($metodo === MetodoEvaluacion::Clasica) {
            return [
                'probabilidad_respuestas' => 'required|array|size:5',
                'probabilidad_respuestas.*' => 'required|integer|min:0|max:2',
                'impacto_respuestas' => 'required|array|size:5',
                'impacto_respuestas.*' => 'required|integer|min:0|max:2',
            ];
        }

        $config = config('riesgo_ambiental');
        $reglas = ['ambiental' => 'required|array'];
        foreach (self::EJES_AMBIENTALES as $eje => $ruta) {
            $valores = array_column(data_get($config, $ruta.'.opciones'), 'v');
            $reglas["ambiental.$eje"] = ['required', 'integer', Rule::in($valores)];
        }
        foreach (['impacto', 'financiera'] as $lista) {
            $reglas["ambiental.materialidad_$lista"] = 'nullable|array';
            $reglas["ambiental.materialidad_$lista.*"] = ['integer', 'distinct', Rule::in(array_keys($config['materialidad'][$lista]['items']))];
        }

        return $reglas;
    }

    /**
     * Caracterización (temporalidad, condición, responsabilidad): obligatoria en
     * el método ambiental. En el clásico no se valida y el controlador la deja
     * en null. Test: un_riesgo_ambiental_exige_la_caracterizacion.
     */
    public static function reglasCaracterizacion(MetodoEvaluacion $metodo): array
    {
        if ($metodo !== MetodoEvaluacion::Ambiental) {
            return [];
        }

        return [
            'temporalidad' => ['required', Rule::enum(Temporalidad::class)],
            'condicion' => ['required', Rule::enum(Condicion::class)],
            'responsabilidad' => ['required', Rule::enum(Responsabilidad::class)],
        ];
    }

    /** Arma la evaluación a guardar con lo que ya pasó por reglas(). */
    public static function desdeRequest(MetodoEvaluacion $metodo, array $validado): array
    {
        if ($metodo === MetodoEvaluacion::Clasica) {
            $respuestas = [
                'probabilidad' => array_map('intval', $validado['probabilidad_respuestas']),
                'impacto' => array_map('intval', $validado['impacto_respuestas']),
            ];
        } else {
            $a = $validado['ambiental'];
            $respuestas = [];
            foreach (array_keys(self::EJES_AMBIENTALES) as $eje) {
                $respuestas[$eje] = (int) $a[$eje];
            }
            foreach (['materialidad_impacto', 'materialidad_financiera'] as $lista) {
                // Ordenadas, para que dos evaluaciones iguales comparen iguales.
                $ids = array_map('intval', $a[$lista] ?? []);
                sort($ids);
                $respuestas[$lista] = $ids;
            }
        }

        return ['metodo' => $metodo->value, 'respuestas' => $respuestas];
    }

    /**
     * Impacto y probabilidad que da una evaluación.
     * Clásica: cada eje es la suma de sus 5 respuestas (0-2), de 0 a 10.
     * Ambiental: probabilidad = control + frecuencia (2 a 10). Impacto = medio +
     * naturaleza + magnitud (3 a 10) más un extra por materialidades marcadas
     * (ver extraMaterialidad()), con tope 10.
     * Tests: recalcular_actualiza_impacto_y_probabilidad_segun_las_respuestas,
     * el_alta_guarda_la_evaluacion_y_calcula_con_ella,
     * el_impacto_ambiental_suma_el_extra_y_no_pasa_de_10.
     *
     * @return array{probabilidad:int, impacto:int}
     */
    public static function calcular(array $evaluacion): array
    {
        $r = $evaluacion['respuestas'];

        if (MetodoEvaluacion::from($evaluacion['metodo']) === MetodoEvaluacion::Clasica) {
            return [
                'probabilidad' => array_sum($r['probabilidad']),
                'impacto' => array_sum($r['impacto']),
            ];
        }

        return [
            'probabilidad' => $r['control'] + $r['frecuencia'],
            'impacto' => min(10, $r['medio'] + $r['naturaleza'] + $r['magnitud'] + self::extraMaterialidad($r)),
        ];
    }

    /**
     * Puntos que suman al impacto ambiental las materialidades marcadas, las dos
     * listas juntas: ninguna → 0, de 1 a 10 → 1, más de 10 → 2.
     * Test: el_extra_por_materialidades_va_de_0_a_2.
     */
    public static function extraMaterialidad(array $respuestas): int
    {
        $marcadas = count($respuestas['materialidad_impacto'] ?? []) + count($respuestas['materialidad_financiera'] ?? []);

        return match (true) {
            $marcadas === 0 => 0,
            $marcadas <= 10 => 1,
            default => 2,
        };
    }

    /**
     * La evaluación vigente de un riesgo reconstruida desde su historial: las
     * respuestas de la última alta o recálculo que se aplicó. Si no explican el
     * número actual (lo cambiaron a mano mientras rigió D-015), devuelve null y
     * el wizard arranca vacío. La usa la migración que agrega `evaluacion`, que
     * corre antes de que exista el método ambiental: sólo reconoce el clásico.
     * Tests: el_backfill_toma_las_respuestas_del_ultimo_recalculo_aplicado,
     * el_backfill_descarta_respuestas_que_no_explican_el_numero.
     */
    public static function desdeHistorial(int $riesgoId, int $impacto, int $probabilidad): ?array
    {
        $estados = DB::table('estados')->pluck('nombre', 'id');

        $filas = DB::table('actualizaciones')
            ->where('actualizable_type', Riesgo::class)
            ->where('actualizable_id', $riesgoId)
            ->orderByDesc('id')
            ->get(['data', 'estado_id']);

        foreach ($filas as $fila) {
            $data = json_decode((string) $fila->data, true) ?? [];
            if (empty($data['respuestas']['probabilidad']) || empty($data['respuestas']['impacto'])) {
                continue;
            }
            // Un recálculo fuera de borrador sólo cuenta si se aplicó.
            $aplicada = ($data['tipo'] ?? null) !== 'cambio'
                || isset($data['activated_by'])
                || ($estados[$fila->estado_id] ?? null) === 'aprobado';
            if (! $aplicada) {
                continue;
            }

            $evaluacion = ['metodo' => MetodoEvaluacion::Clasica->value, 'respuestas' => [
                'probabilidad' => array_map('intval', $data['respuestas']['probabilidad']),
                'impacto' => array_map('intval', $data['respuestas']['impacto']),
            ]];

            return self::calcular($evaluacion) === ['probabilidad' => $probabilidad, 'impacto' => $impacto]
                ? $evaluacion
                : null;
        }

        return null;
    }

    /** Las respuestas en una línea, para el historial y las propuestas. */
    public static function resumen(?array $evaluacion): string
    {
        if (empty($evaluacion['respuestas'])) {
            return '—';
        }
        $r = $evaluacion['respuestas'];

        return match (MetodoEvaluacion::tryFrom($evaluacion['metodo'] ?? '')) {
            MetodoEvaluacion::Clasica => 'Prob. '.implode('·', $r['probabilidad']).' · Imp. '.implode('·', $r['impacto']),
            MetodoEvaluacion::Ambiental => sprintf(
                'Control %d + Frec. %d · Medio %d + Nat. %d + Mag. %d · %d materialidades (+%d)',
                $r['control'], $r['frecuencia'], $r['medio'], $r['naturaleza'], $r['magnitud'],
                count($r['materialidad_impacto'] ?? []) + count($r['materialidad_financiera'] ?? []),
                self::extraMaterialidad($r),
            ),
            default => '—',
        };
    }
}
