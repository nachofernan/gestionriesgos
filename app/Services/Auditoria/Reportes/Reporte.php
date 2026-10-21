<?php

namespace App\Services\Auditoria\Reportes;

use App\Models\Auditoria\Area;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Base de los reportes del panel (D-033). Todos son de sólo lectura y comparten
 * el universo del panel: sesgo gerencial (Riesgo::scopeDeCascadaArea, D-007),
 * nunca borradores ni borrados, y los filtros de FiltrosReporte. Cada subclase
 * devuelve arrays planos que consumen tanto la pestaña Livewire como el Excel:
 * ningún número se recalcula fuera de acá, y el valor del riesgo sale siempre
 * de los accessors del modelo.
 */
abstract class Reporte
{
    public const CRITICIDADES = ['critico' => 'Crítico', 'moderado' => 'Moderado', 'bajo' => 'Bajo'];

    public const SITUACIONES_PLAN = ['sin_plan' => 'Sin plan', 'vigente' => 'Plan vigente', 'vencido' => 'Plan vencido'];

    /** Gerencia (nombre) por area_id, resuelta en memoria una sola vez. */
    private ?array $gerencias = null;

    public function __construct(
        protected readonly User $user,
        protected readonly FiltrosReporte $filtros,
    ) {}

    abstract public function datos(): array;

    /**
     * Riesgos del panel con todo lo que necesitan los accessors de valor y los
     * cortes de los reportes, eager-loaded.
     */
    protected function riesgos(): Collection
    {
        $estados = $this->filtros->soloAprobados ? ['aprobado'] : ['aprobado', 'validado'];

        return $this->consultaRiesgos()
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', $estados))
            ->with(['estado', 'tipoRiesgo', 'naturalezas', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->orderBy('nombre')
            ->get();
    }

    /** Cascada del usuario + filtros de tipo y respuesta, sin restricción de estado. */
    protected function consultaRiesgos(): Builder
    {
        return Riesgo::deCascadaArea($this->user)
            ->when($this->filtros->tipoId, fn ($q, $id) => $q->where('tipo_riesgo_id', $id))
            ->when($this->filtros->respuesta, fn ($q, $r) => $q->where('respuesta', $r));
    }

    /**
     * Planes de acción de la cascada del usuario (por su propia área), vigentes
     * según el toggle del panel. Mismo sesgo gerencial que los riesgos.
     */
    protected function consultaPlanes(): Builder
    {
        $estados = $this->filtros->soloAprobados ? ['aprobado'] : ['aprobado', 'validado'];

        return PlanAccion::query()
            ->when($this->user->area_id, fn ($q) => $q->whereIn('area_id', $this->user->idsAreasGestionables()))
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', $estados));
    }

    /**
     * Nombre de la gerencia a la que pertenece un área (Area::gerencia(), pero
     * sobre el árbol cargado en memoria para no pegar una consulta por riesgo).
     * Lo que cuelga de la raíz cae en la raíz, que también está marcada como
     * gerencia ("Comité de Riesgo").
     */
    protected function gerenciaDe(?int $areaId): string
    {
        if ($this->gerencias === null) {
            $areas = Area::all()->keyBy('id');
            $this->gerencias = $areas->map(function (Area $area) use ($areas) {
                while ($area) {
                    if ($area->esGerencia()) {
                        return $area->nombre;
                    }
                    $area = $areas->get($area->area_padre_id);
                }

                return 'Sin gerencia';
            })->all();
        }

        return $areaId ? ($this->gerencias[$areaId] ?? 'Sin gerencia') : 'Sin área';
    }

    /**
     * Situación del riesgo respecto de sus planes de acción vigentes (validados o
     * aprobados): sin plan, vencido si alguno está vencido (PlanAccion::esta_vencido)
     * o vigente.
     */
    protected function situacionPlan(Riesgo $riesgo): string
    {
        $planes = $riesgo->planesAccion->filter(
            fn ($p) => in_array($p->estado?->nombre, ['validado', 'aprobado'])
        );

        if ($planes->isEmpty()) {
            return 'sin_plan';
        }

        return $planes->contains(fn ($p) => $p->esta_vencido) ? 'vencido' : 'vigente';
    }

    /**
     * Conteos de un grupo de riesgos en todos los cortes que usan los reportes
     * (a) y (b): total, por tipo, por naturaleza (N:M, no suma el total), por
     * gerencia, criticidad inherente y residual, y situación de plan.
     */
    protected function conteos(Collection $riesgos): array
    {
        $fila = [
            'total' => $riesgos->count(),
            'tipos' => [],
            'naturalezas' => [],
            'gerencias' => [],
            'inherente' => array_fill_keys(array_keys(self::CRITICIDADES), 0),
            'residual' => array_fill_keys(array_keys(self::CRITICIDADES), 0),
            'plan' => array_fill_keys(array_keys(self::SITUACIONES_PLAN), 0),
        ];

        foreach ($riesgos as $r) {
            $tipo = $r->tipoRiesgo?->nombre ?? 'Sin tipo';
            $fila['tipos'][$tipo] = ($fila['tipos'][$tipo] ?? 0) + 1;

            foreach ($r->naturalezas as $n) {
                $fila['naturalezas'][$n->nombre] = ($fila['naturalezas'][$n->nombre] ?? 0) + 1;
            }

            $gerencia = $this->gerenciaDe($r->area_id);
            $fila['gerencias'][$gerencia] = ($fila['gerencias'][$gerencia] ?? 0) + 1;

            $fila['inherente'][$r->clasificacion_total['etiqueta']]++;
            $fila['residual'][$r->clasificacion_residual['etiqueta']]++;
            $fila['plan'][$this->situacionPlan($r)]++;
        }

        return $fila;
    }
}
