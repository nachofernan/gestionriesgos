<?php

namespace App\Services\Auditoria\Reportes;

use App\Models\Auditoria\PlanAccion;

/**
 * Reporte (d): planes de acción y sus tareas por gerencia. Por plan: los riesgos
 * que mitiga, avance (PlanAccion::avance), vencimiento (PlanAccion::vencimiento),
 * si está en fecha o vencido y si tiene avance (> 0) o no. Por gerencia, los
 * subtotales de planes y de tareas. Los filtros de tipo/respuesta del panel son
 * de riesgos y acá no aplican; el toggle de estados sí.
 * Tests: ReportesTest (el_reporte_de_planes_*).
 */
class ReportePlanes extends Reporte
{
    public function datos(): array
    {
        $planes = $this->consultaPlanes()
            ->with(['estado', 'tareas.estado', 'riesgos' => fn ($q) => $q->whereHas(
                'estado', fn ($e) => $e->whereIn('nombre', ['validado', 'aprobado'])
            )->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        $filas = $planes->map(fn (PlanAccion $p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'gerencia' => $this->gerenciaDe($p->area_id),
            'estado' => $p->estado?->nombre,
            'riesgos' => $p->riesgos->pluck('nombre')->all(),
            'avance' => $p->avance,
            'vencimiento' => $p->vencimiento,
            'vencido' => $p->esta_vencido,
            'con_avance' => ($p->avance ?? 0) > 0,
        ])->sortBy('gerencia')->values();

        $tareas = $planes->flatMap(fn (PlanAccion $p) => $p->tareas
            ->reject(fn ($t) => $t->estado?->nombre === 'borrado')
            ->map(fn ($t) => [
                'plan' => $p->nombre,
                'gerencia' => $this->gerenciaDe($p->area_id),
                'nombre' => $t->nombre,
                'estado' => $t->estado?->nombre,
                'fecha' => $t->fecha,
                'avance' => (int) $t->porcentaje_avance,
                'vencida' => $t->fecha && $t->porcentaje_avance < 100 && $t->fecha->lt(today()),
            ]))->sortBy([['gerencia', 'asc'], ['plan', 'asc']])->values();

        $gerencias = $filas->pluck('gerencia')->unique()->sort()->values()->map(function ($g) use ($filas, $tareas) {
            $p = $filas->where('gerencia', $g);
            $t = $tareas->where('gerencia', $g);

            return [
                'nombre' => $g,
                'planes' => $p->count(),
                'planes_en_fecha' => $p->where('vencido', false)->count(),
                'planes_vencidos' => $p->where('vencido', true)->count(),
                'planes_con_avance' => $p->where('con_avance', true)->count(),
                'planes_sin_avance' => $p->where('con_avance', false)->count(),
                'riesgos' => $p->pluck('riesgos')->flatten()->unique()->count(),
                'tareas' => $t->count(),
                'tareas_vencidas' => $t->where('vencida', true)->count(),
                'tareas_con_avance' => $t->where('avance', '>', 0)->count(),
                'tareas_sin_avance' => $t->where('avance', 0)->count(),
            ];
        });

        return [
            'gerencias' => $gerencias->all(),
            'planes' => $filas->all(),
            'tareas' => $tareas->all(),
        ];
    }
}
