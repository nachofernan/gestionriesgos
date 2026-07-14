<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

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
 * mitigación ya persistida de los controles.
 */
class GestionPlanes extends Component
{
    public int $riesgoId;

    public int $valorTotal = 0;

    public bool $modalAbierto = false;

    public bool $editando = false;

    public bool $esBorrador = true;

    public string $estadoModelo = 'borrador';

    public string $busqueda = '';

    /** Mitigación de los controles ya asociados, base fija del preview de residual. */
    public int $mitigacionControlesBase = 0;

    /** @var array<int, array{id:int, codigo:string, nombre:string, mitigacion:int, avg_avance:?int}> */
    public array $seleccionados = [];

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
        $this->valorTotal = $riesgo->valor_total;
        $this->cargar();
    }

    public function activarEdicion(): void
    {
        $this->editando = true;
    }

    public function cancelarEdicion(): void
    {
        $this->cargar();
        $this->editando = false;
        $this->busqueda = '';
        $this->modalAbierto = false;
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

        $plan = PlanAccion::with(['estado', 'area', 'tareas'])->find($planId);
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

        $this->cerrarModal();
        $this->dispatch('residual-actualizado', valor: $this->residualActual());
    }

    public function quitar(int $planId): void
    {
        $this->seleccionados = array_values(
            array_filter($this->seleccionados, fn ($p) => $p['id'] !== $planId)
        );
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
        $riesgo = Riesgo::with('planesAccion')->findOrFail($this->riesgoId);

        $sync = [];
        foreach ($this->seleccionados as $item) {
            $sync[(string) $item['id']] = ['mitigacion' => $item['mitigacion']];
        }

        if ($this->esBorrador) {
            $riesgo->planesAccion()->sync($sync);
            $this->editando = false;
            $this->dispatch('residual-actualizado', valor: $this->residualActual());
            session()->flash('ok', 'Planes de acción actualizados.');
        } else {
            $estadoId = $this->estadoParaActualizacion();

            $antesMap = $riesgo->planesAccion->mapWithKeys(fn ($p) => [$p->id => ['nombre' => $p->nombre, 'mitigacion' => $p->pivot->mitigacion]]);
            $antesIds = $antesMap->keys();
            $despuesIds = collect($this->seleccionados)->pluck('id');

            $diffRel = array_filter([
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

            $data = ['tipo' => 'cambio', 'relaciones' => ['planesAccion' => ['sync' => $sync]]];
            if (! empty($diffRel)) {
                $data['diff'] = ['relaciones' => ['planesAccion' => $diffRel]];
            }

            $aplicarAhora = $estadoId === Estado::aprobado()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado');

            if ($aplicarAhora) {
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
                $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Propuesta de cambio en planes de acción asociados',
                    'estado_id' => $estadoId,
                    'data' => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Propuesta registrada. Pendiente de validación.');
            }
        }
    }

    /**
     * Residual = valor total menos la mitigación ya persistida de los controles
     * menos la de los planes seleccionados que estén al 100% de avance (no persiste,
     * es sólo feedback en vivo). Un plan por debajo del 100% no descuenta nada.
     */
    private function residualActual(): int
    {
        $mitigacionPlanes = collect($this->seleccionados)
            ->filter(fn ($p) => ($p['avg_avance'] ?? null) === 100)
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
        $riesgo = Riesgo::with(['planesAccion.estado', 'planesAccion.area', 'planesAccion.tareas', 'controles', 'estado'])->findOrFail($this->riesgoId);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $this->esBorrador = $this->estadoModelo === 'borrador';

        $this->mitigacionControlesBase = (int) $riesgo->controles->sum(fn ($c) => $c->pivot->mitigacion ?? $c->mitigacion_default);

        $user = Auth::user();
        $this->seleccionados = $riesgo->planesAccion->map(function ($p) use ($user) {
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
                'puede_ver' => $user->can('view', $p),
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
                ->visiblePara(Auth::user())
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
