<?php

namespace App\Livewire\Auditoria;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Livewire\Component;

/**
 * Panel único con tabs para listar y hacer CRUD/asociaciones rápidas de las 5
 * entidades del módulo (riesgo/control/objetivo/plan/tarea) mediante un modal
 * genérico. A diferencia del resto del módulo, este componente escribe directo
 * (sin pasar por el flujo de estados borrador/validado/aprobado ni por
 * Actualizaciones) y sus guardarX()/actualizarX() no llaman a $this->authorize()
 * ni usan Auth::id() (usan `user_id => 1` fijo) — revisar antes de dejarlo
 * expuesto a usuarios reales.
 */
class Dashboard extends Component
{
    // -------------------------------------------------------
    // Tab activa
    // -------------------------------------------------------
    public string $tabActiva = 'riesgos';

    // -------------------------------------------------------
    // Modal genérico
    // -------------------------------------------------------
    public bool $modalAbierto = false;

    public string $modalTipo = ''; // crear_riesgo | editar_riesgo | crear_control | editar_control | crear_objetivo | editar_objetivo | crear_plan | editar_plan | crear_tarea | editar_tarea
    // asociar_control | asociar_objetivo | asociar_tarea_riesgo
    // asociar_tarea_plan

    // -------------------------------------------------------
    // Edición
    // -------------------------------------------------------
    public ?int $entidadEditandoId = null;

    // -------------------------------------------------------
    // Formularios de creación
    // -------------------------------------------------------
    public array $form = [];

    // -------------------------------------------------------
    // Asociaciones
    // -------------------------------------------------------
    public ?int $entidadSeleccionadaId = null; // ID del riesgo/plan al que se asocia

    public array $idsParaAsociar = [];          // checkboxes seleccionados

    // -------------------------------------------------------
    // Abrir modales de creación/edición
    // -------------------------------------------------------
    public function abrirModalCrear(string $tipo): void
    {
        $this->form = [];
        $this->entidadEditandoId = null;
        $this->modalTipo = $tipo;
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(string $tipo, int $entidadId): void
    {
        $this->entidadEditandoId = $entidadId;
        $this->cargarDatosEntidad($tipo, $entidadId);
        $this->modalTipo = 'editar_'.$tipo;
        $this->modalAbierto = true;
    }

    private function cargarDatosEntidad(string $tipo, int $entidadId): void
    {
        match ($tipo) {
            'riesgo' => $this->cargarRiesgo($entidadId),
            'control' => $this->cargarControl($entidadId),
            'objetivo' => $this->cargarObjetivo($entidadId),
            'plan' => $this->cargarPlan($entidadId),
            'tarea' => $this->cargarTarea($entidadId),
            default => null,
        };
    }

    private function cargarRiesgo(int $id): void
    {
        $riesgo = Riesgo::findOrFail($id);
        $this->form = [
            'nombre' => $riesgo->nombre,
            'descripcion' => $riesgo->descripcion,
            'impacto' => $riesgo->impacto,
            'probabilidad' => $riesgo->probabilidad,
            'tipo_riesgo_id' => $riesgo->tipo_riesgo_id,
        ];
    }

    private function cargarControl(int $id): void
    {
        $control = Control::findOrFail($id);
        $this->form = [
            'nombre' => $control->nombre,
            'descripcion' => $control->descripcion,
            'mitigacion_default' => $control->mitigacion_default,
        ];
    }

    private function cargarObjetivo(int $id): void
    {
        $objetivo = Objetivo::findOrFail($id);
        $this->form = [
            'nombre' => $objetivo->nombre,
            'descripcion' => $objetivo->descripcion,
            'fecha_objetivo' => $objetivo->fecha_objetivo?->format('Y-m-d'),
        ];
    }

    private function cargarPlan(int $id): void
    {
        $plan = PlanAccion::findOrFail($id);
        $this->form = [
            'codigo' => $plan->codigo,
            'nombre' => $plan->nombre,
            'descripcion' => $plan->descripcion,
            'riesgo_id' => $plan->riesgo_id,
        ];
    }

    private function cargarTarea(int $id): void
    {
        $tarea = Tarea::findOrFail($id);
        $this->form = [
            'nombre' => $tarea->nombre,
            'descripcion' => $tarea->descripcion,
            'porcentaje_avance' => $tarea->porcentaje_avance,
        ];
    }

    // -------------------------------------------------------
    // Abrir modales de asociación
    // -------------------------------------------------------
    public function abrirModalAsociar(string $tipo, int $entidadId): void
    {
        $this->entidadSeleccionadaId = $entidadId;
        $this->cargarAsociacionesActuales($tipo, $entidadId);
        $this->modalTipo = $tipo;
        $this->modalAbierto = true;
    }

    private function cargarAsociacionesActuales(string $tipo, int $entidadId): void
    {
        match ($tipo) {
            'asociar_control' => $this->cargarControlesDeRiesgo($entidadId),
            'asociar_objetivo' => $this->cargarObjetivosDeRiesgo($entidadId),
            'asociar_tarea_riesgo' => $this->cargarTareasDeRiesgo($entidadId),
            'asociar_tarea_plan' => $this->cargarTareasDePlan($entidadId),
            default => null,
        };
    }

    private function cargarControlesDeRiesgo(int $riesgoId): void
    {
        $riesgo = Riesgo::findOrFail($riesgoId);
        $this->idsParaAsociar = $riesgo->controles()->pluck('control_id')->toArray();
    }

    private function cargarObjetivosDeRiesgo(int $riesgoId): void
    {
        $riesgo = Riesgo::findOrFail($riesgoId);
        $this->idsParaAsociar = $riesgo->objetivos()->pluck('objetivo_id')->toArray();
    }

    private function cargarTareasDeRiesgo(int $riesgoId): void
    {
        $riesgo = Riesgo::with('planesAccion.tareas')->findOrFail($riesgoId);
        $this->idsParaAsociar = $riesgo->planesAccion->flatMap->tareas->pluck('id')->unique()->toArray();
    }

    private function cargarTareasDePlan(int $planId): void
    {
        $plan = PlanAccion::findOrFail($planId);
        $this->idsParaAsociar = $plan->tareas->pluck('id')->toArray();
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->modalTipo = '';
        $this->form = [];
        $this->idsParaAsociar = [];
        $this->entidadSeleccionadaId = null;
        $this->entidadEditandoId = null;
    }

    // -------------------------------------------------------
    // Guardar entidades
    // -------------------------------------------------------
    public function guardar(): void
    {
        match ($this->modalTipo) {
            'crear_riesgo' => $this->guardarRiesgo(),
            'editar_riesgo' => $this->actualizarRiesgo(),
            'crear_control' => $this->guardarControl(),
            'editar_control' => $this->actualizarControl(),
            'crear_objetivo' => $this->guardarObjetivo(),
            'editar_objetivo' => $this->actualizarObjetivo(),
            'crear_plan' => $this->guardarPlan(),
            'editar_plan' => $this->actualizarPlan(),
            'crear_tarea' => $this->guardarTarea(),
            'editar_tarea' => $this->actualizarTarea(),
            default => null,
        };
    }

    private function guardarRiesgo(): void
    {
        $this->validate([
            'form.nombre' => 'required|string|max:255',
            'form.impacto' => 'required|integer|min:0|max:10',
            'form.probabilidad' => 'required|integer|min:0|max:10',
            'form.tipo_riesgo_id' => 'required|exists:tipos_riesgo,id',
        ]);

        Riesgo::create([
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'impacto' => $this->form['impacto'],
            'probabilidad' => $this->form['probabilidad'],
            'tipo_riesgo_id' => $this->form['tipo_riesgo_id'],
            'user_id' => 1,
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Riesgo creado.');
    }

    private function actualizarRiesgo(): void
    {
        $this->validate([
            'form.nombre' => 'required|string|max:255',
            'form.impacto' => 'required|integer|min:0|max:10',
            'form.probabilidad' => 'required|integer|min:0|max:10',
            'form.tipo_riesgo_id' => 'required|exists:tipos_riesgo,id',
        ]);

        $riesgo = Riesgo::findOrFail($this->entidadEditandoId);
        $riesgo->update([
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'impacto' => $this->form['impacto'],
            'probabilidad' => $this->form['probabilidad'],
            'tipo_riesgo_id' => $this->form['tipo_riesgo_id'],
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Riesgo actualizado.');
    }

    private function guardarControl(): void
    {
        $this->validate([
            'form.nombre' => 'required|string|max:255',
            'form.mitigacion_default' => 'required|integer|min:1|max:10',
        ]);

        Control::create([
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'mitigacion_default' => $this->form['mitigacion_default'],
            'user_id' => 1,
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Control creado.');
    }

    private function actualizarControl(): void
    {
        $this->validate([
            'form.nombre' => 'required|string|max:255',
            'form.mitigacion_default' => 'required|integer|min:1|max:10',
        ]);

        $control = Control::findOrFail($this->entidadEditandoId);
        $control->update([
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'mitigacion_default' => $this->form['mitigacion_default'],
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Control actualizado.');
    }

    private function guardarObjetivo(): void
    {
        $this->validate([
            'form.nombre' => 'required|string|max:255',
        ]);

        Objetivo::create([
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'fecha_objetivo' => $this->form['fecha_objetivo'] ?? null,
            'user_id' => 1,
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Objetivo creado.');
    }

    private function actualizarObjetivo(): void
    {
        $this->validate([
            'form.nombre' => 'required|string|max:255',
        ]);

        $objetivo = Objetivo::findOrFail($this->entidadEditandoId);
        $objetivo->update([
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'fecha_objetivo' => $this->form['fecha_objetivo'] ?? null,
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Objetivo actualizado.');
    }

    private function guardarPlan(): void
    {
        $this->validate([
            'form.codigo' => 'required|string|max:100|unique:planes_accion,codigo',
            'form.nombre' => 'required|string|max:255',
            'form.riesgo_id' => 'required|exists:riesgos,id',
        ]);

        PlanAccion::create([
            'codigo' => $this->form['codigo'],
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'riesgo_id' => $this->form['riesgo_id'],
            'user_id' => 1,
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Plan de acción creado.');
    }

    private function actualizarPlan(): void
    {
        $this->validate([
            'form.codigo' => 'required|string|max:100|unique:planes_accion,codigo,'.$this->entidadEditandoId,
            'form.nombre' => 'required|string|max:255',
            'form.riesgo_id' => 'required|exists:riesgos,id',
        ]);

        $plan = PlanAccion::findOrFail($this->entidadEditandoId);
        $plan->update([
            'codigo' => $this->form['codigo'],
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'riesgo_id' => $this->form['riesgo_id'],
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Plan de acción actualizado.');
    }

    private function guardarTarea(): void
    {
        $this->validate([
            'form.nombre' => 'required|string|max:255',
            'form.porcentaje_avance' => 'required|integer|min:0|max:100',
        ]);

        Tarea::create([
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'porcentaje_avance' => $this->form['porcentaje_avance'] ?? 0,
            'user_id' => 1,
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Tarea creada.');
    }

    private function actualizarTarea(): void
    {
        $this->validate([
            'form.nombre' => 'required|string|max:255',
            'form.porcentaje_avance' => 'required|integer|min:0|max:100',
        ]);

        $tarea = Tarea::findOrFail($this->entidadEditandoId);
        $tarea->update([
            'nombre' => $this->form['nombre'],
            'descripcion' => $this->form['descripcion'] ?? null,
            'porcentaje_avance' => $this->form['porcentaje_avance'] ?? 0,
        ]);

        $this->cerrarModal();
        session()->flash('ok', 'Tarea actualizada.');
    }

    // -------------------------------------------------------
    // Asociar
    // -------------------------------------------------------
    public function asociar(): void
    {
        match ($this->modalTipo) {
            'asociar_control' => $this->asociarControlARiesgo(),
            'asociar_objetivo' => $this->asociarObjetivoARiesgo(),
            'asociar_tarea_riesgo' => $this->asociarTareaARiesgo(),
            'asociar_tarea_plan' => $this->asociarTareaAPlan(),
            default => null,
        };
    }

    private function finalizarAsociacion(string $tipoVolver, int $idVolver): void
    {
        $this->abrirModalEditar($tipoVolver, $idVolver);
    }

    private function asociarControlARiesgo(): void
    {
        $riesgo = Riesgo::findOrFail($this->entidadSeleccionadaId);
        $riesgo->controles()->sync($this->idsParaAsociar);
        $this->finalizarAsociacion('riesgo', $riesgo->id);
        session()->flash('ok', 'Controles actualizados.');
    }

    private function asociarObjetivoARiesgo(): void
    {
        $riesgo = Riesgo::findOrFail($this->entidadSeleccionadaId);
        $riesgo->objetivos()->sync($this->idsParaAsociar);
        $this->finalizarAsociacion('riesgo', $riesgo->id);
        session()->flash('ok', 'Objetivos actualizados.');
    }

    private function asociarTareaAPlan(): void
    {
        $plan = PlanAccion::findOrFail($this->entidadSeleccionadaId);
        $plan->tareas()->sync($this->idsParaAsociar);
        $this->finalizarAsociacion('plan', $plan->id);
        session()->flash('ok', 'Tareas actualizadas.');
    }

    // -------------------------------------------------------
    // Sugerencia de código para Plan de Acción
    // -------------------------------------------------------
    public function sugerirCodigo(): void
    {
        $ultimo = PlanAccion::withTrashed()->orderByDesc('id')->first();
        $numero = $ultimo ? (intval(preg_replace('/\D/', '', $ultimo->codigo)) + 1) : 1;
        $this->form['codigo'] = 'PA-'.str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------
    // Render
    // -------------------------------------------------------
    public function render()
    {
        $data = [
            'tiposRiesgo' => TipoRiesgo::all(),
            'usuarios' => User::select('id', 'name')->get(),
        ];

        if ($this->tabActiva === 'riesgos') {
            // controles.estado y planesAccion.tareas.estado: los usa el accessor
            // valor_residual que muestra el listado de riesgos.
            $data['riesgos'] = Riesgo::with(['tipoRiesgo', 'controles.estado', 'planesAccion.tareas.estado', 'objetivos', 'user'])
                ->latest()->get();
        }

        if ($this->tabActiva === 'controles') {
            $data['controles'] = Control::with(['riesgos', 'user'])->latest()->get();
        }

        if ($this->tabActiva === 'objetivos') {
            $data['objetivos'] = Objetivo::with(['riesgos', 'user'])->latest()->get();
        }

        if ($this->tabActiva === 'planes') {
            $data['planes'] = PlanAccion::with(['tareas', 'riesgo', 'user'])->latest()->get();
        }

        if ($this->tabActiva === 'tareas') {
            $data['tareas'] = Tarea::with(['planesAccion.riesgo', 'user'])->latest()->get();
        }

        // Para modales de asociación necesitamos listas completas y la entidad actual
        if ($this->modalAbierto) {
            $data['todosControles'] = Control::all();
            $data['todosObjetivos'] = Objetivo::all();
            $data['todasTareas'] = Tarea::all();
            $data['todosRiesgos'] = Riesgo::all();

            if ($this->entidadEditandoId) {
                $data['entidadActual'] = match (str_replace('editar_', '', $this->modalTipo)) {
                    'riesgo' => Riesgo::with(['controles', 'objetivos', 'planesAccion.tareas'])->find($this->entidadEditandoId),
                    'control' => Control::with(['riesgos'])->find($this->entidadEditandoId),
                    'objetivo' => Objetivo::with(['riesgos'])->find($this->entidadEditandoId),
                    'plan' => PlanAccion::with(['tareas', 'riesgo'])->find($this->entidadEditandoId),
                    'tarea' => Tarea::with(['planesAccion.riesgo'])->find($this->entidadEditandoId),
                    default => null
                };
            }
        }

        return view('livewire.auditoria.dashboard', $data);
    }
}
