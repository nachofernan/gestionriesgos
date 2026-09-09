<?php

namespace Database\Seeders\CargaInicial;

use App\Models\Auditoria\PlanAccion;
use Illuminate\Database\Seeder;

/**
 * Pivot plan_accion_tarea desde PlanAccionTarea.csv. En este dataset cada tarea
 * pertenece a un único plan, pero la relación es muchos-a-muchos igual que en el
 * resto del sistema.
 */
class PlanAccionTareaCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    public function run(): void
    {
        foreach ($this->filasCsv('PlanAccionTarea.csv') as [$planCsvId, $tareaCsvId]) {
            $planId = MapeoCargaInicial::$planAccionIdPorCsvId[(int) $planCsvId] ?? null;
            $tareaId = MapeoCargaInicial::$tareaIdPorCsvId[(int) $tareaCsvId] ?? null;
            if (! $planId || ! $tareaId) {
                continue;
            }

            PlanAccion::find($planId)->tareas()->syncWithoutDetaching([$tareaId]);
        }
    }
}
