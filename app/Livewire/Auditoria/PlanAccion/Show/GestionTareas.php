<?php

namespace App\Livewire\Auditoria\PlanAccion\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
 * También permite crear una tarea nueva, de cualquier área, desde un modal
 * (guardarNuevaTarea(), D-030): queda asociada en el acto y sigue su propio ciclo.
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

    /** Ver PlanAccionPolicy::crearTarea(): quien gestiona el plan, salvo el auditor. */
    public bool $puedeCrearTarea = false;

    public string $estadoModelo = 'borrador';

    public string $busqueda = '';

    /** Modal de tarea nueva abierto. */
    public bool $creandoTarea = false;

    /** @var array{nombre:string, descripcion:string, fecha:string, porcentaje_avance:int, area_id:string, user_id:string} */
    public array $nueva = [];

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
        $this->puedeCrearTarea = Auth::user()->can('crearTarea', $plan);
        $this->resetFormNuevaTarea();
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

    public function abrirFormNuevaTarea(): void
    {
        $this->authorize('crearTarea', PlanAccion::findOrFail($this->planId));
        $this->resetFormNuevaTarea();
        $this->resetValidation();
        $this->modalAbierto = false;
        $this->creandoTarea = true;
    }

    public function cerrarFormNuevaTarea(): void
    {
        $this->creandoTarea = false;
        $this->resetFormNuevaTarea();
        $this->resetValidation();
    }

    /** Arranca con el área del usuario como sugerencia; se puede elegir cualquiera (D-030). */
    private function resetFormNuevaTarea(): void
    {
        $this->nueva = [
            'nombre' => '',
            'descripcion' => '',
            'fecha' => '',
            'porcentaje_avance' => 0,
            'area_id' => (string) (Auth::user()->area_id ?? ''),
            'user_id' => '',
        ];
    }

    /** Al cambiar el área, el responsable elegido deja de valer si no corresponde a la nueva. */
    public function updatedNueva(mixed $valor, string $clave): void
    {
        if ($clave === 'area_id' && ! in_array((int) $this->nueva['user_id'], $this->idsResponsables($valor))) {
            $this->nueva['user_id'] = '';
        }
    }

    /** Usuarios que pueden ser responsables de una tarea del área (Area::idsAreasDeResponsables()). */
    private function idsResponsables(mixed $areaId): array
    {
        $area = $areaId ? Area::find($areaId) : null;

        return $area
            ? User::whereIn('area_id', $area->idsAreasDeResponsables())->pluck('id')->all()
            : [];
    }

    /**
     * Crea una tarea desde el plan, para cualquier área, y la asocia al plan en el
     * acto (D-030): el vínculo no es una propuesta sobre el plan, sigue el destino
     * de la tarea (si la rechazan queda "borrado" y se oculta; el avance sólo cuenta
     * las aprobadas). Nace validada si quien la crea es gerente (o comité) y el área
     * es de su línea; si no, en borrador, y la valida el gerente de esa área.
     * Tests: TareaDesdePlanTest (un_empleado_crea_desde_su_plan_una_tarea_de_otra_area,
     * un_gerente_crea_una_tarea_de_su_linea_y_nace_validada,
     * un_gerente_que_crea_para_otra_area_la_deja_en_borrador).
     */
    public function guardarNuevaTarea(): void
    {
        $plan = PlanAccion::findOrFail($this->planId);
        $this->authorize('crearTarea', $plan);

        $this->validate([
            'nueva.nombre' => 'required|string|max:255',
            'nueva.descripcion' => 'nullable|string',
            'nueva.fecha' => 'nullable|date',
            'nueva.porcentaje_avance' => 'required|integer|min:0|max:100',
            'nueva.area_id' => 'required|exists:areas,id',
            'nueva.user_id' => ['nullable', Rule::in($this->idsResponsables($this->nueva['area_id'] ?? null))],
        ], [
            'nueva.nombre.required' => 'El nombre de la tarea es obligatorio.',
            'nueva.area_id.required' => 'Debe elegir un área.',
            'nueva.user_id.in' => 'El responsable tiene que pertenecer al área elegida, a una de sus sub-áreas o a un área superior de su gerencia.',
        ]);

        $user = Auth::user();
        $areaId = (int) $this->nueva['area_id'];
        $estado = $user->esGerente() && $user->puedeGestionarArea($areaId) ? Estado::validado() : Estado::borrador();

        $tarea = DB::transaction(function () use ($plan, $user, $areaId, $estado) {
            $tarea = Tarea::create([
                'nombre' => $this->nueva['nombre'],
                'descripcion' => $this->nueva['descripcion'] ?: null,
                'fecha' => $this->nueva['fecha'] ?: null,
                'porcentaje_avance' => (int) $this->nueva['porcentaje_avance'],
                'area_id' => $areaId,
                'user_id' => $this->nueva['user_id'] ?: null,
                'estado_id' => $estado->id,
            ]);
            $tarea->actualizaciones()->create([
                'user_id' => $user->id,
                'mensaje' => "Tarea creada desde el plan «{$plan->nombre}»",
                'estado_id' => $estado->id,
                'data' => ['tipo' => 'creacion', 'campos' => [
                    'nombre' => $tarea->nombre,
                    'descripcion' => $tarea->descripcion,
                    'porcentaje_avance' => $tarea->porcentaje_avance,
                    'fecha' => $tarea->fecha?->format('Y-m-d'),
                ]],
            ]);

            $plan->tareas()->attach($tarea->id);
            $plan->actualizaciones()->create([
                'user_id' => $user->id,
                'mensaje' => "Tarea «{$tarea->nombre}» creada desde el plan",
                'estado_id' => $estado->id,
                // Registro de algo ya hecho, no una propuesta: el vínculo sigue a la tarea.
                'data' => ['tipo' => 'edicion', 'activated_by' => $user->name, 'diff' => ['relaciones' => ['tareas' => [
                    'agrega' => [['id' => $tarea->id, 'nombre' => $tarea->nombre]],
                ]]]],
            ]);

            return $tarea;
        });

        $this->cerrarFormNuevaTarea();
        if (! $this->editando) {
            $this->cargar();
        } else {
            // A mitad de una edición: se suma a lo que está armando, ya asociada.
            $this->agregar($tarea->id);
        }
        $this->dispatch('plan-actualizado');
        session()->flash('ok', $estado->nombre === 'validado'
            ? 'Tarea creada y agregada al plan.'
            : 'Tarea creada en borrador y agregada al plan. La valida el gerente de su área.');
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

        if ($this->modoCambio() === 'directo') {
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
            $this->proponerPorElemento($plan, 'tareas', $diffRel, $estadoId, 'tarea');
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
            'modo' => $this->modoCambio(),
            'bloqueados' => $bloqueados,
            'propuestas' => $propuestas,
            'marcas' => $this->marcasDe($propuestas, 'tareas'),
            'diffEnCurso' => $this->editando ? $this->construirDiff($plan) : [],
            'resultados' => $resultados,
            // Modal de tarea nueva: cualquier área (D-030), y los responsables de la elegida.
            'areasNueva' => $this->creandoTarea ? Area::orderBy('nombre')->get(['id', 'nombre']) : collect(),
            'responsablesNueva' => $this->creandoTarea
                ? User::whereIn('id', $this->idsResponsables($this->nueva['area_id']))->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }
}
