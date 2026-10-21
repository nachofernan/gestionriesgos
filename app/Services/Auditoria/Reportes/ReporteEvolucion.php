<?php

namespace App\Services\Auditoria\Reportes;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reporte (c): evolución entre dos fechas (el "entre sesiones del comité" del
 * pedido, resuelto con un rango libre: D-033). Dos partes:
 *
 * - Movimiento de riesgos: cuántos estaban aprobados al inicio, cuántos se
 *   aprobaron y cuántos se rechazaron en el período, y cuántos quedan al final,
 *   abierto por tipo y por gerencia. Sale de Riesgo::aprobado_en/rechazado_en; los
 *   aprobados sin fecha (carga inicial) se cuentan como previos y se informan.
 * - Avance de planes: avance de cada plan al inicio y al final, reconstruido
 *   desde el historial de cambios de porcentaje_avance de sus tareas.
 *
 * Tests: ReportesTest (la_evolucion_*).
 */
class ReporteEvolucion extends Reporte
{
    public function datos(): array
    {
        $riesgos = $this->consultaRiesgos()
            ->where(fn ($q) => $q->whereNotNull('aprobado_en')
                ->orWhereNotNull('rechazado_en')
                ->orWhereHas('estado', fn ($e) => $e->where('nombre', 'aprobado')))
            ->with(['estado', 'tipoRiesgo'])
            ->orderBy('aprobado_en')
            ->get();

        return [
            'desde' => $this->filtros->desde,
            'hasta' => $this->filtros->hasta,
            'resumen' => $this->movimiento($riesgos),
            'porTipo' => $this->movimientoPor($riesgos, fn ($r) => $r->tipoRiesgo?->nombre ?? 'Sin tipo'),
            'porGerencia' => $this->movimientoPor($riesgos, fn ($r) => $this->gerenciaDe($r->area_id)),
            'sinFecha' => $riesgos->filter(fn ($r) => $r->aprobado_en === null && $r->estado?->nombre === 'aprobado')->count(),
            'aprobados' => $riesgos
                ->filter(fn ($r) => $r->aprobado_en?->between($this->filtros->desde, $this->filtros->hasta))
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'nombre' => $r->nombre,
                    'tipo' => $r->tipoRiesgo?->nombre,
                    'gerencia' => $this->gerenciaDe($r->area_id),
                    'aprobado_en' => $r->aprobado_en,
                    'valor_total' => $r->valor_total,
                ])->values()->all(),
            'planes' => $this->avancePlanes(),
        ];
    }

    private function movimiento(Collection $riesgos): array
    {
        $desde = $this->filtros->desde;
        $hasta = $this->filtros->hasta;

        return [
            'inicio' => $riesgos->filter(fn ($r) => $this->aprobadoAl($r, $desde, estricto: true))->count(),
            'aprobados' => $riesgos->filter(fn ($r) => $r->aprobado_en?->between($desde, $hasta))->count(),
            'rechazados' => $riesgos->filter(fn ($r) => $r->rechazado_en?->between($desde, $hasta))->count(),
            'final' => $riesgos->filter(fn ($r) => $this->aprobadoAl($r, $hasta))->count(),
        ];
    }

    private function movimientoPor(Collection $riesgos, callable $clave): array
    {
        return $riesgos->groupBy($clave)
            ->sortKeys()
            ->map(fn ($grupo, $nombre) => ['nombre' => $nombre] + $this->movimiento($grupo))
            ->values()
            ->all();
    }

    /**
     * Si el riesgo estaba aprobado (y no rechazado) a esa fecha. Un aprobado sin
     * aprobado_en (anterior a que existiera la columna) cuenta como de siempre.
     */
    private function aprobadoAl(Riesgo $r, Carbon $fecha, bool $estricto = false): bool
    {
        $aprobado = $r->aprobado_en
            ? ($estricto ? $r->aprobado_en->lt($fecha) : $r->aprobado_en->lte($fecha))
            : $r->estado?->nombre === 'aprobado';

        return $aprobado && ! ($r->rechazado_en && $r->rechazado_en->lte($fecha));
    }

    /**
     * Avance de cada plan de la cascada al inicio y al final del período, más las
     * tareas que llegaron al 100% en el medio. Se toma el conjunto actual de
     * tareas aprobadas del plan (como PlanAccion::avance) y el avance de cada una
     * en cada fecha se reconstruye con avanceAl().
     */
    private function avancePlanes(): array
    {
        $planes = $this->consultaPlanes()
            ->with(['tareas.estado', 'tareas.actualizaciones'])
            ->orderBy('nombre')
            ->get();

        return $planes->map(function ($plan) {
            $tareas = $plan->tareas->filter(fn ($t) => $t->estado?->nombre === 'aprobado');
            $promedio = fn (Carbon $fecha) => $tareas->isEmpty()
                ? null
                : (int) round($tareas->avg(fn ($t) => $this->avanceAl($t, $fecha)));

            $inicio = $promedio($this->filtros->desde->copy()->subSecond());
            $final = $promedio($this->filtros->hasta);

            return [
                'id' => $plan->id,
                'nombre' => $plan->nombre,
                'gerencia' => $this->gerenciaDe($plan->area_id),
                'avance_inicio' => $inicio,
                'avance_final' => $final,
                'variacion' => $inicio === null ? null : $final - $inicio,
                'tareas_completadas' => $tareas->filter(
                    fn ($t) => $this->avanceAl($t, $this->filtros->hasta) === 100
                        && $this->avanceAl($t, $this->filtros->desde->copy()->subSecond()) < 100
                )->count(),
            ];
        })
            ->sortBy([['gerencia', 'asc'], ['variacion', 'desc']])
            ->values()
            ->all();
    }

    /**
     * Avance de una tarea a una fecha: parte del valor actual y deshace, del más
     * nuevo al más viejo, cada cambio de porcentaje_avance que se aplicó después de
     * esa fecha. Un cambio aplicado es una edición directa de borrador o una
     * propuesta activada (activated_by); su fecha efectiva es la de aprobación, o la
     * de validación si nunca se aprobó. Si el historial no registró un cambio, la
     * reconstrucción lo pierde: queda el valor actual.
     */
    private function avanceAl(Tarea $tarea, Carbon $fecha): int
    {
        $avance = (int) $tarea->porcentaje_avance;

        $cambios = $tarea->actualizaciones
            ->map(fn (Actualizacion $a) => [
                'cambio' => $a->data['diff']['campos']['porcentaje_avance'] ?? null,
                'aplicado' => ($a->data['tipo'] ?? null) === 'edicion' || isset($a->data['activated_by']),
                'fecha' => $a->aprobado_en ?? $a->validado_en ?? $a->created_at,
            ])
            ->filter(fn ($c) => $c['cambio'] !== null && $c['aplicado'] && $c['fecha']->gt($fecha))
            ->sortByDesc('fecha');

        foreach ($cambios as $c) {
            $avance = (int) $c['cambio']['antes'];
        }

        return $avance;
    }
}
