<?php

namespace Database\Seeders\CargaInicial;

use App\Models\Auditoria\PlanAccion;
use Illuminate\Database\Seeder;

/**
 * Crea los 62 planes de acción de PlanesAccion.csv. El CSV repite la misma fila
 * una vez por cada riesgo asociado —el negocio no sabía que podía separar con
 * comas y duplicó filas para no perderse, ver docs/reconstruccion.md sección
 * 9— así que acá se deduplica por id, quedándose con la primera fila de cada
 * plan. El vínculo real con los riesgos se toma de PlanAccionRiesgo.csv, no de
 * la columna "riesgo id" repetida de este archivo. La mitigación de todos los
 * pares plan-riesgo queda en 0 (decisión de negocio): ningún plan descuenta
 * valor_residual todavía, es carga manual pendiente vía UI. El `codigo`
 * (PA-0001...) replica el algoritmo de PlanAccionController::generarCodigo().
 */
class PlanAccionCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    public function run(): void
    {
        $estadoAprobadoId = MapeoCargaInicial::estado('aprobado');
        $numero = 0;

        foreach ($this->filasCsv('PlanesAccion.csv') as $fila) {
            [$csvId, $nombre, $descripcion, $areaTxt, $userTxt] = $fila;

            if (isset(MapeoCargaInicial::$planAccionIdPorCsvId[(int) $csvId])) {
                continue; // ya creado: esta fila solo repite el plan para otro riesgo
            }

            $numero++;
            $plan = PlanAccion::create([
                'codigo' => 'PA-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT),
                'nombre' => $nombre,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'estado_id' => $estadoAprobadoId,
                'user_id' => MapeoCargaInicial::usuario($userTxt),
                'area_id' => MapeoCargaInicial::area($areaTxt),
            ]);

            MapeoCargaInicial::$planAccionIdPorCsvId[(int) $csvId] = $plan->id;
        }

        $this->sembrarPlanAccionRiesgo();
    }

    private function sembrarPlanAccionRiesgo(): void
    {
        foreach ($this->filasCsv('PlanAccionRiesgo.csv') as [$planCsvId, $riesgoCsvId]) {
            $planId = MapeoCargaInicial::$planAccionIdPorCsvId[(int) $planCsvId] ?? null;
            $riesgoId = MapeoCargaInicial::$riesgoIdPorCsvId[(int) $riesgoCsvId] ?? null;
            if (! $planId || ! $riesgoId) {
                continue;
            }

            PlanAccion::find($planId)->riesgos()->syncWithoutDetaching([$riesgoId]);
        }
    }
}
