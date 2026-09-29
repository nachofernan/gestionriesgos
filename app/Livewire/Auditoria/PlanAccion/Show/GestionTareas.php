<?php

namespace App\Livewire\Auditoria\PlanAccion\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Tarea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Gestión de las Tareas asociadas a un Plan de Acción: patrón $seleccionados en
 * memoria → guardar(). Mismo modelo que los bloques de riesgo/show (D-016): en
 * borrador o en modo directo sincroniza; si el cambio queda como propuesta, cada
 * alta o baja es su propia Actualizacion ('agregar' / 'detach'), que se valida o
 * rechaza por separado, y una tarea con una propuesta pendiente no se puede volver
 * a tocar hasta resolverla. Como el avance del plan sale de sus tareas, una
 * propuesta no mueve ni el avance ni la mitigación hasta aplicarse.
 * También permite crear una tarea nueva sobre la marcha (guardarNuevaTarea()).
 * Dispatcha 'plan-actualizado' al persistir un cambio real, para que InfoPlan y
 * GestionActualizaciones (bloques hermanos en la misma pantalla) se refresquen solos.
 */
class GestionTareas extends Component
{
    use PropuestasEnBloque;

    public int $planId;

    public bool $modalAbierto = false;

    public bool $editando = false;

    public bool $esBorrador = true;

    /** Gobierna la visibilidad de los botones de mutar (Editar / Proponer cambio) en la vista. */
    public bool $puedeActualizar = false;

    /**
     * Crear una tarea nueva desde el bloque es un alta directa, no una propuesta:
     * pide gestionar el plan y poder crear (el auditor proponer sí, crear no; D-020).
     */
    public bool $puedeCrearTarea = false;

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
        $this->puedeActualizar = Auth::user()->can('proponer', $plan);
        $this->puedeCrearTarea = Auth::user()->can('update', $plan) && Auth::user()->can('create', Tarea::class);
        $this->cargar();
    }

    /**
     * Otro bloque (o el historial) cambió el plan: se recarga lo vigente, salvo
     * que el usuario esté editando, para no pisarle lo que tiene a medio armar.
     */
    #[On('plan-actualizado')]
    public function refrescar(): void
    {
        if (! $this->editando) {
            $this->cargar();
        }
    }

    public function activarEdicion(): void
    {
        $this->authorize('proponer', PlanAccion::findOrFail($this->planId));
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
        $this->authorize('update', PlanAccion::findOrFail($this->planId));
        $this->authorize('create', Tarea::class);

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
        if ($this->elementoConPropuesta('tareas', $tareaId)) {
            return;
        }

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
        if ($this->elementoConPropuesta('tareas', $tareaId)) {
            return;
        }

        $this->seleccionados = array_values(
            array_filter($this->seleccionados, fn ($t) => $t['id'] !== $tareaId)
        );
    }

    /**
     * En borrador sincroniza y deja rastro en el historial. Fuera de borrador, en
     * modo directo sincroniza y registra el cambio ya aplicado; si no, arma una
     * propuesta por cada tarea agregada o quitada (proponerPorElemento()).
     * Tests: un_empleado_que_agrega_y_quita_tareas_genera_una_propuesta_por_elemento,
     * una_tarea_propuesta_no_mueve_el_avance_del_plan_hasta_aplicarse,
     * gestion_tareas_guardar_devuelve_403_para_gerente_de_otra_gerencia.
     */
    public function guardar(): void
    {
        $plan = PlanAccion::with('tareas.estado')->findOrFail($this->planId);
        $this->authorize('proponer', $plan);
        // Las tareas "borrado" ocultas se re-agregan al sync para no detacharlas.
        $ids = array_values(array_unique(array_merge(
            collect($this->seleccionados)->pluck('id')->toArray(),
            $this->ocultosIds
        )));
        $diffRel = $this->construirDiff($plan);

        if ($this->esBorrador) {
            $plan->tareas()->sync($ids);
            if (! empty($diffRel)) {
                $plan->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Tareas asociadas',
                    'estado_id' => Estado::borrador()->id,
                    'data' => ['tipo' => 'edicion', 'diff' => ['relaciones' => ['tareas' => $diffRel]]],
                ]);
            }
            $this->dispatch('plan-actualizado');
            $this->cancelarEdicion();
            session()->flash('ok', 'Tareas actualizadas.');

            return;
        }

        if (empty($diffRel)) {
            $this->cancelarEdicion();

            return;
        }

        $estadoId = $this->estadoParaActualizacion();

        if ($this->modoCambio($plan) === 'directo') {
            // Se aplica en el acto: activated_by lo rotula "aplicado" en el historial.
            $plan->tareas()->sync($ids);
            $plan->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Tareas asociadas actualizadas',
                'estado_id' => $estadoId,
                'data' => [
                    'tipo' => 'cambio',
                    'relaciones' => ['tareas' => ['sync' => $ids]],
                    'diff' => ['relaciones' => ['tareas' => $diffRel]],
                    'activated_by' => Auth::user()->name,
                ],
            ]);
            session()->flash('ok', 'Tareas actualizadas.');
        } else {
            $this->proponerPorElemento($plan, 'tareas', $diffRel, $estadoId, false, 'tarea');
            session()->flash('ok', 'Propuesta registrada. Pendiente de validación.');
        }

        $this->dispatch('plan-actualizado');
        $this->cancelarEdicion();
    }

    /**
     * Diff (agrega/quita) entre las tareas vigentes del plan y la selección en
     * memoria. Compara sólo contra las vigentes (no "borrado") para no proponer
     * quitar las ocultas, que se preservan. Vacío si nada cambió.
     */
    private function construirDiff(PlanAccion $plan): array
    {
        $antes = $plan->tareas
            ->reject(fn ($t) => $t->estado?->nombre === 'borrado')
            ->map(fn ($t) => ['id' => $t->id, 'nombre' => $t->nombre]);
        $antesIds = $antes->pluck('id');
        $despues = collect($this->seleccionados)->map(fn ($t) => ['id' => $t['id'], 'nombre' => $t['nombre']]);
        $despuesIds = $despues->pluck('id');

        return array_filter([
            'agrega' => $despues->reject(fn ($t) => $antesIds->contains($t['id']))->values()->toArray(),
            'quita' => $antes->reject(fn ($t) => $despuesIds->contains($t['id']))->values()->toArray(),
        ], fn ($a) => ! empty($a));
    }

    /** Ver PropuestasEnBloque::elementoConPropuesta(). */
    private function entidadDelBloque(): Model
    {
        return PlanAccion::findOrFail($this->planId);
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
            ->sortBy(fn ($t) => Estado::peso($t->estado))
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
        $plan = PlanAccion::with('tareas.estado')->findOrFail($this->planId);
        $propuestas = $this->propuestasDe($plan, 'tareas');
        $bloqueados = $this->idsConPropuesta($propuestas, 'tareas');
        $yaIds = collect($this->seleccionados)->pluck('id')->merge($bloqueados);

        $resultados = $this->modalAbierto
            ? Tarea::query()
                ->with(['estado', 'area'])
                ->visiblePara(Auth::user())
                ->whereNot('tareas.estado_id', Estado::borrado()->id)
                ->when($this->busqueda, fn ($q) => $q->where('tareas.nombre', 'like', '%'.$this->busqueda.'%'))
                ->whereNotIn('tareas.id', $yaIds)
                ->join('estados', 'estados.id', '=', 'tareas.estado_id')
                ->select('tareas.*')
                ->orderByRaw(Estado::ordenSql().' asc')
                ->orderBy('tareas.nombre')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.auditoria.plan-accion.show.gestion-tareas', [
            'modo' => $this->modoCambio($plan),
            'bloqueados' => $bloqueados,
            'propuestas' => $propuestas,
            'marcas' => $this->marcasDe($propuestas, 'tareas'),
            'diffEnCurso' => $this->editando ? $this->construirDiff($plan) : [],
            'resultados' => $resultados,
        ]);
    }
}
