<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use Livewire\Component;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Estado;
use Illuminate\Support\Facades\Auth;

/**
 * Gestión de los Planes de Acción asociados a un Riesgo (patrón $seleccionados
 * en memoria → guardar()). Si el riesgo está en borrador, sincroniza directo; si
 * no, la asociación queda como una Actualizacion (propuesta de cambio) que se
 * aplica de inmediato sólo si el estado resultante lo amerita (ver
 * estadoParaActualizacion()).
 */
class GestionPlanes extends Component
{
    public int $riesgoId;
    public bool $modalAbierto = false;
    public bool $editando = false;
    public bool $esBorrador = true;
    public string $estadoModelo = 'borrador';
    public string $busqueda = '';

    /** @var array<int, array{id:int, codigo:string, nombre:string}> */
    public array $seleccionados = [];

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
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

    public function agregar(int $planId): void
    {
        if (collect($this->seleccionados)->contains('id', $planId)) {
            return;
        }

        $plan = PlanAccion::with(['estado', 'area', 'tareas'])->find($planId);
        if (!$plan) return;

        $vencimiento = $plan->tareas->whereNotNull('fecha')->max('fecha');
        $this->seleccionados[] = [
            'id'           => $plan->id,
            'codigo'       => $plan->codigo ?? '—',
            'nombre'       => $plan->nombre,
            'descripcion'  => $plan->descripcion,
            'estado'       => $plan->estado?->nombre ?? 'borrador',
            'estado_color' => $plan->estado?->color ?? 'gray',
            'area'         => $plan->area?->nombre,
            'avg_avance'   => $plan->tareas->count() ? (int)round($plan->tareas->avg('porcentaje_avance')) : null,
            'tareas_count' => $plan->tareas->count(),
            'vencimiento'  => $vencimiento ? \Carbon\Carbon::parse($vencimiento)->format('d/m/Y') : null,
            'puede_ver'    => Auth::user()->can('view', $plan),
            'url'          => route('auditoria.planes.show', $plan->id),
        ];

        $this->cerrarModal();
    }

    public function quitar(int $planId): void
    {
        $this->seleccionados = array_values(
            array_filter($this->seleccionados, fn($p) => $p['id'] !== $planId)
        );
    }

    /**
     * Si el riesgo está en borrador, sincroniza los planes de inmediato. Si no,
     * arma el diff (agrega/quita) y lo guarda como Actualizacion; según el estado
     * que le toque (estadoParaActualizacion()) la aplica en el momento o la deja
     * pendiente de validación.
     */
    public function guardar(): void
    {
        $riesgo = Riesgo::findOrFail($this->riesgoId);
        $ids = collect($this->seleccionados)->pluck('id')->toArray();

        if ($this->esBorrador) {
            $riesgo->planesAccion()->sync($ids);
            $this->editando = false;
            session()->flash('ok', 'Planes de acción actualizados.');
        } else {
            $estadoId = $this->estadoParaActualizacion();

            $riesgo->load('planesAccion');
            $antesItems = $riesgo->planesAccion->map(fn($p) => ['id' => $p->id, 'nombre' => $p->nombre]);
            $antesIds   = $antesItems->pluck('id');
            $despues    = collect($this->seleccionados)->map(fn($p) => ['id' => $p['id'], 'nombre' => $p['nombre']]);

            $diffRel = array_filter([
                'agrega' => $despues->filter(fn($p) => !$antesIds->contains($p['id']))->values()->toArray(),
                'quita'  => $antesItems->filter(fn($p) => !$despues->pluck('id')->contains($p['id']))->values()->toArray(),
            ], fn($a) => !empty($a));

            $data = ['tipo' => 'cambio', 'relaciones' => ['planesAccion' => ['sync' => $ids]]];
            if (!empty($diffRel)) $data['diff'] = ['relaciones' => ['planesAccion' => $diffRel]];

            $aplicarAhora = $estadoId === Estado::aprobado()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado');

            if ($aplicarAhora) {
                $riesgo->planesAccion()->sync($ids);
                $riesgo->actualizaciones()->create([
                    'user_id'   => Auth::id(),
                    'mensaje'   => 'Planes de acción asociados actualizados',
                    'estado_id' => $estadoId,
                    'data'      => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Planes de acción actualizados.');
            } else {
                $riesgo->actualizaciones()->create([
                    'user_id'   => Auth::id(),
                    'mensaje'   => 'Propuesta de cambio en planes de acción asociados',
                    'estado_id' => $estadoId,
                    'data'      => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Propuesta registrada. Pendiente de validación.');
            }
        }
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
        $riesgo = Riesgo::with(['planesAccion.estado', 'planesAccion.area', 'planesAccion.tareas', 'estado'])->findOrFail($this->riesgoId);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $this->esBorrador   = $this->estadoModelo === 'borrador';

        $user = Auth::user();
        $this->seleccionados = $riesgo->planesAccion->map(function ($p) use ($user) {
            $vencimiento = $p->tareas->whereNotNull('fecha')->max('fecha');
            return [
                'id'           => $p->id,
                'codigo'       => $p->codigo ?? '—',
                'nombre'       => $p->nombre,
                'descripcion'  => $p->descripcion,
                'estado'       => $p->estado?->nombre ?? 'borrador',
                'estado_color' => $p->estado?->color ?? 'gray',
                'area'         => $p->area?->nombre,
                'avg_avance'   => $p->tareas->count() ? (int)round($p->tareas->avg('porcentaje_avance')) : null,
                'tareas_count' => $p->tareas->count(),
                'vencimiento'  => $vencimiento ? \Carbon\Carbon::parse($vencimiento)->format('d/m/Y') : null,
                'puede_ver'    => $user->can('view', $p),
                'url'          => route('auditoria.planes.show', $p->id),
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
                ->when($this->busqueda, fn($q) => $q->where(function ($q) {
                    $q->where('nombre', 'like', '%' . $this->busqueda . '%')
                      ->orWhere('codigo', 'like', '%' . $this->busqueda . '%');
                }))
                ->whereNotIn('id', $yaIds)
                ->orderBy('nombre')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.auditoria.riesgo.show.gestion-planes', [
            'planesConTareas' => $planesConTareas,
            'resultados'      => $resultados,
        ]);
    }
}
