<?php

namespace App\Livewire\Auditoria\PlanAccion\Show;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Tarea;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Gestión de las Tareas asociadas a un Plan de Acción: patrón $seleccionados en
 * memoria → guardar(). Si el plan está en borrador, sincroniza directo; si no,
 * la asociación queda como una Actualizacion (propuesta de cambio) que se aplica
 * de inmediato sólo si el estado resultante lo amerita (ver estadoParaActualizacion()).
 * También permite crear una tarea nueva sobre la marcha (guardarNuevaTarea()).
 */
class GestionTareas extends Component
{
    public int $planId;

    public bool $modalAbierto = false;

    public bool $editando = false;

    public bool $esBorrador = true;

    public string $estadoModelo = 'borrador';

    public string $busqueda = '';

    public bool $creandoTarea = false;

    public string $nuevaNombre = '';

    public string $nuevaFecha = '';

    public int $nuevaPorcentaje = 0;

    /** @var array<int, array{id:int, nombre:string, porcentaje_avance:int, fecha:string|null}> */
    public array $seleccionados = [];

    /**
     * IDs de tareas asociadas en estado "borrado" (rechazadas): no se muestran ni se
     * listan, pero se preservan en el pivot al guardar para no detacharlas ni
     * generar una propuesta de cambio espuria. Siguen siendo visibles desde el
     * listado de Tareas.
     *
     * @var array<int, int>
     */
    public array $ocultosIds = [];

    public function mount(PlanAccion $plan): void
    {
        $this->planId = $plan->id;
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
        $this->creandoTarea = false;
        $this->resetFormNuevaTarea();
    }

    public function abrirModal(): void
    {
        $this->busqueda = '';
        $this->modalAbierto = true;
        $this->creandoTarea = false;
    }

    public function cerrarModal(): void
    {
        $this->busqueda = '';
        $this->modalAbierto = false;
    }

    public function abrirFormNuevaTarea(): void
    {
        $this->creandoTarea = true;
        $this->modalAbierto = false;
        $this->resetFormNuevaTarea();
    }

    public function cerrarFormNuevaTarea(): void
    {
        $this->creandoTarea = false;
        $this->resetFormNuevaTarea();
    }

    private function resetFormNuevaTarea(): void
    {
        $this->nuevaNombre = '';
        $this->nuevaFecha = '';
        $this->nuevaPorcentaje = 0;
    }

    /**
     * Crea una tarea nueva ya en estado aprobado (no pasa por borrador) y la agrega
     * directo a $seleccionados, para asociarla al plan en el guardar() posterior.
     */
    public function guardarNuevaTarea(): void
    {
        $this->validate([
            'nuevaNombre' => 'required|string|max:255',
            'nuevaFecha' => 'nullable|date',
            'nuevaPorcentaje' => 'required|integer|min:0|max:100',
        ], [
            'nuevaNombre.required' => 'El nombre de la tarea es obligatorio.',
        ]);

        $tarea = Tarea::create([
            'nombre' => $this->nuevaNombre,
            'fecha' => $this->nuevaFecha ?: null,
            'porcentaje_avance' => $this->nuevaPorcentaje,
            'user_id' => Auth::id(),
        ]);
        $tarea->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Tarea creada',
            'estado_id' => Estado::aprobado()->id,
            'data' => ['campos' => ['nombre' => $tarea->nombre, 'porcentaje_avance' => $tarea->porcentaje_avance]],
        ]);

        $this->seleccionados[] = [
            'id' => $tarea->id,
            'nombre' => $tarea->nombre,
            'descripcion' => null,
            'porcentaje_avance' => $tarea->porcentaje_avance,
            'fecha' => $tarea->fecha?->format('Y-m-d'),
            'estado' => 'borrador',
            'estado_color' => 'gray',
            'area' => null,
            'user' => Auth::user()->name,
            'puede_ver' => true,
            'url' => route('auditoria.tareas.show', $tarea->id),
        ];

        $this->cerrarFormNuevaTarea();
    }

    public function agregar(int $tareaId): void
    {
        if (collect($this->seleccionados)->contains('id', $tareaId)) {
            return;
        }

        $tarea = Tarea::with(['estado', 'area', 'user'])->find($tareaId);
        if (! $tarea) {
            return;
        }

        $this->seleccionados[] = [
            'id' => $tarea->id,
            'nombre' => $tarea->nombre,
            'descripcion' => $tarea->descripcion,
            'porcentaje_avance' => $tarea->porcentaje_avance,
            'fecha' => $tarea->fecha?->format('Y-m-d'),
            'estado' => $tarea->estado?->nombre ?? 'borrador',
            'estado_color' => $tarea->estado?->color ?? 'gray',
            'area' => $tarea->area?->nombre,
            'user' => $tarea->user?->name,
            'puede_ver' => Auth::user()->can('view', $tarea),
            'url' => route('auditoria.tareas.show', $tarea->id),
        ];

        $this->cerrarModal();
    }

    public function quitar(int $tareaId): void
    {
        $this->seleccionados = array_values(
            array_filter($this->seleccionados, fn ($t) => $t['id'] !== $tareaId)
        );
    }

    /**
     * Si el plan está en borrador, sincroniza las tareas de inmediato. Si no,
     * arma el diff (agrega/quita) y lo guarda como Actualizacion; según el estado
     * que le toque a esa actualización (estadoParaActualizacion()) la aplica en
     * el momento o la deja pendiente de validación.
     */
    public function guardar(): void
    {
        $plan = PlanAccion::findOrFail($this->planId);
        // Las tareas "borrado" ocultas se re-agregan al sync para no detacharlas.
        $ids = array_values(array_unique(array_merge(
            collect($this->seleccionados)->pluck('id')->toArray(),
            $this->ocultosIds
        )));

        if ($this->esBorrador) {
            $plan->tareas()->sync($ids);
            $this->editando = false;
            $this->creandoTarea = false;
            session()->flash('ok', 'Tareas actualizadas.');
        } else {
            $estadoId = $this->estadoParaActualizacion();

            // Se comparan sólo las tareas vigentes (no "borrado") para que el diff no
            // proponga quitar las ocultas, que se preservan vía $ids.
            $plan->load('tareas.estado');
            $antesItems = $plan->tareas
                ->reject(fn ($t) => $t->estado?->nombre === 'borrado')
                ->map(fn ($t) => ['id' => $t->id, 'nombre' => $t->nombre]);
            $antesIds = $antesItems->pluck('id');
            $despues = collect($this->seleccionados)->map(fn ($t) => ['id' => $t['id'], 'nombre' => $t['nombre']]);

            $diffRel = array_filter([
                'agrega' => $despues->filter(fn ($t) => ! $antesIds->contains($t['id']))->values()->toArray(),
                'quita' => $antesItems->filter(fn ($t) => ! $despues->pluck('id')->contains($t['id']))->values()->toArray(),
            ], fn ($a) => ! empty($a));

            $data = ['tipo' => 'cambio', 'relaciones' => ['tareas' => ['sync' => $ids]]];
            if (! empty($diffRel)) {
                $data['diff'] = ['relaciones' => ['tareas' => $diffRel]];
            }

            $aplicarAhora = $estadoId === Estado::aprobado()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado');

            if ($aplicarAhora) {
                $plan->tareas()->sync($ids);
                $plan->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Tareas asociadas actualizadas',
                    'estado_id' => $estadoId,
                    'data' => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Tareas actualizadas.');
            } else {
                $plan->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Propuesta de cambio en tareas asociadas',
                    'estado_id' => $estadoId,
                    'data' => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Propuesta registrada. Pendiente de validación.');
            }
        }
    }

    /**
     * Regla de negocio: el comité editando un plan ya aprobado genera la
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
        $plan = PlanAccion::with(['tareas.estado', 'tareas.area', 'tareas.user', 'estado'])->findOrFail($this->planId);
        $this->estadoModelo = $plan->estado?->nombre ?? 'borrador';
        $this->esBorrador = $this->estadoModelo === 'borrador';

        // Las tareas "borrado" quedan fuera de la lista visible pero se recuerdan
        // para preservarlas en el pivot al guardar (ver $ocultosIds y guardar()).
        $this->ocultosIds = $plan->tareas
            ->filter(fn ($t) => $t->estado?->nombre === 'borrado')
            ->pluck('id')->all();

        $user = Auth::user();
        $this->seleccionados = $plan->tareas
            ->reject(fn ($t) => $t->estado?->nombre === 'borrado')
            ->map(fn ($t) => [
                'id' => $t->id,
                'nombre' => $t->nombre,
                'descripcion' => $t->descripcion,
                'porcentaje_avance' => $t->porcentaje_avance,
                'fecha' => $t->fecha?->format('Y-m-d'),
                'estado' => $t->estado?->nombre ?? 'borrador',
                'estado_color' => $t->estado?->color ?? 'gray',
                'area' => $t->area?->nombre,
                'user' => $t->user?->name,
                'puede_ver' => $user->can('view', $t),
                'url' => route('auditoria.tareas.show', $t->id),
            ])->values()->toArray();
    }

    public function render()
    {
        $yaIds = collect($this->seleccionados)->pluck('id');

        $resultados = $this->modalAbierto
            ? Tarea::query()
                ->visiblePara(Auth::user())
                ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', '%'.$this->busqueda.'%'))
                ->whereNotIn('id', $yaIds)
                ->orderBy('nombre')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.auditoria.plan-accion.show.gestion-tareas', [
            'resultados' => $resultados,
        ]);
    }
}
