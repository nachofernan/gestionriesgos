<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Gestión de los Planes de Acción asociados a un Riesgo (patrón $seleccionados
 * en memoria → guardar()), incluyendo el valor de mitigación por plan: lo que el
 * plan descuenta del valor residual del riesgo cuando llega al 100% de avance.
 * Si el riesgo está en borrador, sincroniza directo; si no, la asociación queda
 * como una Actualizacion (propuesta de cambio) que se aplica de inmediato sólo si
 * el estado resultante lo amerita (ver estadoParaActualizacion()). Emite
 * 'residual-actualizado' al cambiar la selección/mitigación para que la vista
 * recalcule el residual sin esperar a guardar; el preview contempla también la
 * mitigación ya persistida de los controles. Si el riesgo tiene respuesta
 * "Reducir/Mitigar" y ya está validado/aprobado, quitar() no deja que se quede
 * sin ningún plan que respalde ese estado (ver $exigePlan).
 */
class GestionPlanes extends Component
{
    public int $riesgoId;

    public int $valorTotal = 0;

    public bool $modalAbierto = false;

    public bool $editando = false;

    public bool $esBorrador = true;

    /** Gobierna la visibilidad de los botones de mutar (Editar / Proponer cambio) en la vista. */
    public bool $puedeActualizar = false;

    public string $estadoModelo = 'borrador';

    public string $busqueda = '';

    public string $error = '';

    /** Riesgo con respuesta "Reducir/Mitigar": quitar() no puede dejarlo sin plan que respalde su estado actual. */
    public bool $exigePlan = false;

    /** Mitigación de los controles ya asociados, base fija del preview de residual. */
    public int $mitigacionControlesBase = 0;

    /** @var array<int, array{id:int, codigo:string, nombre:string, mitigacion:int, avg_avance:?int}> */
    public array $seleccionados = [];

    /**
     * IDs de planes asociados que no se listan: los "borrado" (rechazados, quedan
     * en limbo — ver CLAUDE.md) y los que el usuario actual no puede ver
     * (borrador/validado de un área que no gestiona). Se preservan en el pivot al
     * guardar para no detacharlos silenciosamente (mismo patrón que
     * GestionTareas::$ocultosIds).
     *
     * @var array<int, int>
     */
    public array $ocultosIds = [];

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
        $this->valorTotal = $riesgo->valor_total;
        $this->puedeActualizar = Auth::user()->can('update', $riesgo);
        $this->cargar();
    }

    public function activarEdicion(): void
    {
        $this->editando = true;
        $this->error = '';
    }

    public function cancelarEdicion(): void
    {
        $this->cargar();
        $this->editando = false;
        $this->busqueda = '';
        $this->modalAbierto = false;
        $this->error = '';
        $this->dispatch('residual-actualizado', valor: $this->residualActual());
    }

    public function abrirModal(): void
    {
        $this->busqueda = '';
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->busqueda = '';
        $this->modalAbierto = false;
    }

    /**
     * Ajusta la mitigación que el plan aplicará al llegar al 100% (0-20, acotado al
     * valor_total del riesgo ya que no tiene sentido mitigar más que el riesgo entero).
     */
    public function actualizarMitigacion(int $planId, int $valor): void
    {
        foreach ($this->seleccionados as &$item) {
            if ($item['id'] === $planId) {
                $item['mitigacion'] = max(0, min(20, $valor));
                break;
            }
        }
        $this->dispatch('residual-actualizado', valor: $this->residualActual());
    }

    public function agregar(int $planId): void
    {
        if (collect($this->seleccionados)->contains('id', $planId)) {
            return;
        }

        $plan = PlanAccion::with(['estado', 'area', 'tareas.estado'])->find($planId);
        if (! $plan) {
            return;
        }

        $vencimiento = $plan->tareas->whereNotNull('fecha')->max('fecha');
        $this->seleccionados[] = [
            'id' => $plan->id,
            'codigo' => $plan->codigo ?? '—',
            'nombre' => $plan->nombre,
            'descripcion' => $plan->descripcion,
            'estado' => $plan->estado?->nombre ?? 'borrador',
            'estado_color' => $plan->estado?->color ?? 'gray',
            'area' => $plan->area?->nombre,
            'mitigacion' => 0,
            'avg_avance' => $plan->avance,
            'tareas_count' => $plan->tareas->count(),
            'vencimiento' => $vencimiento ? Carbon::parse($vencimiento)->format('d/m/Y') : null,
            'puede_ver' => Auth::user()->can('view', $plan),
            'url' => route('auditoria.planes.show', $plan->id),
        ];

        $this->error = '';
        $this->cerrarModal();
        $this->dispatch('residual-actualizado', valor: $this->residualActual());
    }

    /**
     * Un riesgo con respuesta "Reducir/Mitigar" ya validado/aprobado no puede
     * quedarse, tras quitar un plan, sin ninguno que respalde su estado actual
     * (validado exige un plan validado o aprobado; aprobado exige uno aprobado
     * — mismo requisito que Riesgo::motivosBloqueoValidacion()/
     * motivosBloqueoAprobacion(), aplicado acá para no poder retroceder por esta
     * vía). En borrador, o con otra respuesta, no hay restricción: recién se
     * exige al intentar validar.
     */
    public function quitar(int $planId): void
    {
        if ($this->exigePlan && ! $this->esBorrador) {
            $estadosQueRespaldan = $this->estadoModelo === 'aprobado' ? ['aprobado'] : ['validado', 'aprobado'];
            $restantes = collect($this->seleccionados)->reject(fn ($p) => $p['id'] === $planId);

            if (! $restantes->contains(fn ($p) => in_array($p['estado'], $estadosQueRespaldan, true))) {
                $this->error = $this->estadoModelo === 'aprobado'
                    ? 'Un riesgo aprobado con respuesta "Reducir/Mitigar" debe conservar al menos un plan de acción aprobado.'
                    : 'Un riesgo validado con respuesta "Reducir/Mitigar" debe conservar al menos un plan de acción validado (o aprobado).';

                return;
            }
        }

        $this->seleccionados = array_values(
            array_filter($this->seleccionados, fn ($p) => $p['id'] !== $planId)
        );
        $this->error = '';
        $this->dispatch('residual-actualizado', valor: $this->residualActual());
    }

    /**
     * Si el riesgo está en borrador, sincroniza los planes y sus mitigaciones de
     * inmediato. Si no, arma el diff (agrega/quita/cambia mitigación) y lo guarda
     * como Actualizacion; según el estado que le toque (estadoParaActualizacion())
     * la aplica en el momento o la deja pendiente de validación.
     */
    public function guardar(): void
    {
        $riesgo = Riesgo::with('planesAccion.estado')->findOrFail($this->riesgoId);
        $this->authorize('update', $riesgo);

        $sync = [];
        foreach ($this->seleccionados as $item) {
            $sync[(string) $item['id']] = ['mitigacion' => $item['mitigacion']];
        }
        // Los ocultos (borrado / no visibles) se re-agregan al sync (mitigación
        // persistida) para no detacharlos.
        foreach ($this->ocultosIds as $id) {
            $antes = $riesgo->planesAccion->firstWhere('id', $id);
            $sync[(string) $id] = ['mitigacion' => $antes?->pivot->mitigacion];
        }

        $diffRel = $this->construirDiff($riesgo);

        if ($this->esBorrador) {
            $riesgo->planesAccion()->sync($sync);

            // Dejar rastro del cambio en el historial también en borrador, pero sólo
            // si la selección realmente cambió (mismo diff que pinta la vista de
            // actualizaciones vía data['diff']['relaciones']).
            if (! empty($diffRel)) {
                $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Planes de acción asociados',
                    'estado_id' => Estado::borrador()->id,
                    'data' => ['tipo' => 'edicion', 'diff' => ['relaciones' => ['planesAccion' => $diffRel]]],
                ]);
            }

            $this->editando = false;
            $this->dispatch('residual-actualizado', valor: $this->residualActual());
            session()->flash('ok', 'Planes de acción actualizados.');
        } else {
            // Riesgo compartido entre gerencias: el cambio no se aplica de una,
            // nace pendiente y el proponente vota a favor por su gerencia (ver
            // Riesgo::cambioRequiereDobleValidacion()).
            $dobleValidacion = $riesgo->cambioRequiereDobleValidacion(Auth::user());
            $estadoId = $dobleValidacion ? Estado::borrador()->id : $this->estadoParaActualizacion();

            $data = ['tipo' => 'cambio', 'relaciones' => ['planesAccion' => ['sync' => $sync]]];
            if (! empty($diffRel)) {
                $data['diff'] = ['relaciones' => ['planesAccion' => $diffRel]];
            }

            $aplicarAhora = ! $dobleValidacion && ($estadoId === Estado::aprobado()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado'));

            if ($aplicarAhora) {
                // El cambio se aplica en el acto: se marca activated_by para que el
                // historial lo rotule "Cambios aplicados" y no "Cambios propuestos".
                $data['activated_by'] = Auth::user()->name;
                $riesgo->planesAccion()->sync($sync);
                $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Planes de acción asociados actualizados',
                    'estado_id' => $estadoId,
                    'data' => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Planes de acción actualizados.');
            } else {
                $actualizacion = $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Propuesta de cambio en planes de acción asociados',
                    'estado_id' => $estadoId,
                    'data' => $data,
                ]);

                if ($dobleValidacion) {
                    $actualizacion->registrarVoto(Auth::user(), true);
                }

                $this->cancelarEdicion();
                session()->flash('ok', 'Propuesta registrada. Pendiente de validación.');
            }
        }
    }

    /**
     * Diff (agrega/quita/cambia mitigación) entre los planes actualmente
     * persistidos en el riesgo y la selección en memoria. Vacío si nada cambió.
     * Lo consumen ambas ramas de guardar() (borrador y propuesta de cambio) y lo
     * pinta el historial de actualizaciones vía data['diff']['relaciones'].
     * Compara sólo contra los planes vigentes (no ocultos, ver cargar()) para que
     * el diff no proponga "quitar" un oculto que en realidad se preserva.
     */
    private function construirDiff(Riesgo $riesgo): array
    {
        $user = Auth::user();
        $antesMap = $riesgo->planesAccion
            ->reject(fn ($p) => $p->estado?->nombre === 'borrado' || ! $user->can('view', $p))
            ->mapWithKeys(fn ($p) => [$p->id => ['nombre' => $p->nombre, 'mitigacion' => $p->pivot->mitigacion]]);
        $antesIds = $antesMap->keys();
        $despuesIds = collect($this->seleccionados)->pluck('id');

        return array_filter([
            'agrega' => collect($this->seleccionados)
                ->filter(fn ($p) => ! $antesIds->contains($p['id']))
                ->map(fn ($p) => ['id' => $p['id'], 'nombre' => $p['nombre'], 'mitigacion' => $p['mitigacion']])
                ->values()->toArray(),
            'quita' => $antesMap->filter(fn ($v, $k) => ! $despuesIds->contains($k))
                ->map(fn ($v, $k) => ['id' => $k, 'nombre' => $v['nombre']])
                ->values()->toArray(),
            'cambia' => collect($this->seleccionados)
                ->filter(fn ($p) => $antesIds->contains($p['id']) && $antesMap[$p['id']]['mitigacion'] !== $p['mitigacion'])
                ->map(fn ($p) => ['id' => $p['id'], 'nombre' => $p['nombre'], 'mitigacion_antes' => $antesMap[$p['id']]['mitigacion'], 'mitigacion_despues' => $p['mitigacion']])
                ->values()->toArray(),
        ], fn ($a) => ! empty($a));
    }

    /**
     * Residual = valor total menos la mitigación ya persistida de los controles
     * menos la de los planes seleccionados que estén aprobados y al 100% de avance
     * (no persiste, es sólo feedback en vivo). Un plan validado/borrador o por
     * debajo del 100% no descuenta nada, misma regla que
     * Riesgo::getValorResidualAttribute.
     */
    private function residualActual(): int
    {
        $mitigacionPlanes = collect($this->seleccionados)
            ->filter(fn ($p) => ($p['estado'] ?? null) === 'aprobado' && ($p['avg_avance'] ?? null) === 100)
            ->sum('mitigacion');

        return max(0, $this->valorTotal - $this->mitigacionControlesBase - $mitigacionPlanes);
    }

    /**
     * Regla de negocio: el comité editando un riesgo ya aprobado genera la
     * actualización directamente en aprobado; gerente o comité en cualquier otro
     * caso saltean el borrador y van a validado; el resto arranca en borrador.
     */
    private function estadoParaActualizacion(): int
    {
        $user = Auth::user();
        if ($this->estadoModelo === 'aprobado' && $user->esComite()) {
            return Estado::aprobado()->id;
        }
        if ($user->esGerente() || $user->esComite()) {
            return Estado::validado()->id;
        }

        return Estado::borrador()->id;
    }

    private function cargar(): void
    {
        $riesgo = Riesgo::with(['planesAccion.estado', 'planesAccion.area', 'planesAccion.tareas.estado', 'controles.estado', 'estado'])->findOrFail($this->riesgoId);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $this->esBorrador = $this->estadoModelo === 'borrador';
        $this->exigePlan = $riesgo->respuesta === RespuestaRiesgo::Mitigar;

        // Sólo los controles aprobados mitigan (misma regla que el accessor valor_residual).
        $this->mitigacionControlesBase = (int) $riesgo->controles
            ->filter(fn ($c) => $c->estado?->nombre === 'aprobado')
            ->sum(fn ($c) => $c->pivot->mitigacion ?? $c->mitigacion_default);

        $user = Auth::user();

        // Los planes "borrado" o no visibles para el usuario actual quedan fuera de
        // la lista pero se recuerdan para preservarlos en el pivot al guardar (ver
        // $ocultosIds y guardar()).
        $this->ocultosIds = $riesgo->planesAccion
            ->filter(fn ($p) => $p->estado?->nombre === 'borrado' || ! $user->can('view', $p))
            ->pluck('id')->all();

        $this->seleccionados = $riesgo->planesAccion
            ->reject(fn ($p) => $p->estado?->nombre === 'borrado' || ! $user->can('view', $p))
            ->map(function ($p) {
                $vencimiento = $p->tareas->whereNotNull('fecha')->max('fecha');

                return [
                    'id' => $p->id,
                    'codigo' => $p->codigo ?? '—',
                    'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion,
                    'estado' => $p->estado?->nombre ?? 'borrador',
                    'estado_color' => $p->estado?->color ?? 'gray',
                    'area' => $p->area?->nombre,
                    'mitigacion' => (int) ($p->pivot->mitigacion ?? 0),
                    'avg_avance' => $p->avance,
                    'tareas_count' => $p->tareas->count(),
                    'vencimiento' => $vencimiento ? Carbon::parse($vencimiento)->format('d/m/Y') : null,
                    'puede_ver' => true,
                    'url' => route('auditoria.planes.show', $p->id),
                ];
            })->values()->toArray();
    }

    public function render()
    {
        $yaIds = collect($this->seleccionados)->pluck('id');

        $planesConTareas = PlanAccion::with(['tareas.estado', 'tareas.area', 'tareas.user'])
            ->whereIn('id', $yaIds)
            ->get()
            ->keyBy('id');

        $resultados = $this->modalAbierto
            ? PlanAccion::query()
                ->with(['estado', 'area', 'tareas.estado'])
                ->visiblePara(Auth::user())
                ->whereNot('estado_id', Estado::borrado()->id)
                ->when($this->busqueda, fn ($q) => $q->where(function ($q) {
                    $q->where('nombre', 'like', '%'.$this->busqueda.'%')
                        ->orWhere('codigo', 'like', '%'.$this->busqueda.'%');
                }))
                ->whereNotIn('id', $yaIds)
                ->orderBy('nombre')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.auditoria.riesgo.show.gestion-planes', [
            'planesConTareas' => $planesConTareas,
            'resultados' => $resultados,
        ]);
    }
}
