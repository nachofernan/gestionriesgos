<?php

namespace App\Livewire\Auditoria\Tarea\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Tarea;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de tarea/show: nombre, descripción, fecha límite y porcentaje de avance,
 * con las propuestas pendientes sobre esos campos debajo. Mismo patrón que
 * FichaObjetivo: fuera de borrador se edita en el bloque y guardar() manda sólo lo
 * que cambió a Actualizacion::registrarCambioCampos(); en borrador se edita con el
 * formulario completo (TareaController::edit). El avance sigue el mismo ciclo que
 * cualquier campo: mientras sea propuesta no mueve el avance del plan
 * (PlanAccion::avance, promedio de las tareas aprobadas) ni su mitigación.
 */
class FichaTarea extends Component
{
    use PropuestasEnBloque;

    public int $tareaId;

    public string $estadoModelo = 'borrador';

    public bool $editando = false;

    /** @var array{nombre:string, descripcion:?string, fecha:?string, porcentaje_avance:int} */
    public array $form = [];

    /** Motivo del cambio: queda como mensaje de la Actualizacion. */
    public string $mensaje = '';

    private const CAMPOS = ['nombre', 'descripcion', 'fecha', 'porcentaje_avance'];

    #[On('tarea-actualizado')]
    public function refrescar(): void {}

    public function mount(Tarea $tarea): void
    {
        $this->tareaId = $tarea->id;
    }

    public function activarEdicion(): void
    {
        $tarea = Tarea::findOrFail($this->tareaId);
        $this->authorize('update', $tarea);

        $this->form = $this->valoresVigentes($tarea);
        $this->mensaje = '';
        $this->resetValidation();
        $this->editando = true;
    }

    public function cancelarEdicion(): void
    {
        $this->editando = false;
        $this->reset(['form', 'mensaje']);
        $this->resetValidation();
    }

    /**
     * Valida en el borde, arma los campos que cambiaron (sin los que tienen una
     * propuesta pendiente) y los registra.
     * Tests: un_empleado_propone_avance_y_no_cuenta_hasta_aplicarse,
     * la_ficha_de_la_tarea_devuelve_403_a_un_gerente_de_otra_gerencia.
     */
    public function guardar(): void
    {
        $tarea = Tarea::with('estado')->findOrFail($this->tareaId);
        $this->authorize('update', $tarea);

        $this->validate([
            'mensaje' => 'required|string|min:3',
            'form.nombre' => 'required|string|max:255',
            'form.descripcion' => 'nullable|string',
            'form.fecha' => 'nullable|date',
            'form.porcentaje_avance' => 'required|integer|min:0|max:100',
        ], [
            'mensaje.required' => 'Contá brevemente por qué se cambia.',
            'form.fecha.date' => 'Ingresá una fecha válida.',
        ]);

        $vigentes = $this->valoresVigentes($tarea);
        $bloqueados = $this->camposConPropuesta($this->propuestasDe($tarea, 'campos'));
        $campos = [];
        foreach (self::CAMPOS as $campo) {
            $nuevo = $this->form[$campo] ?? null;
            $nuevo = $nuevo === '' ? null : $nuevo;
            if (in_array($campo, $bloqueados, true) || (string) $vigentes[$campo] === (string) $nuevo) {
                continue;
            }
            $campos[$campo] = $nuevo;
        }

        if (empty($campos)) {
            $this->addError('form', 'No hay cambios para guardar.');

            return;
        }

        $actualizacion = Actualizacion::registrarCambioCampos($tarea, Auth::user(), $this->mensaje, $campos);

        $this->dispatch('tarea-actualizado');
        $this->cancelarEdicion();
        session()->flash('ok', isset($actualizacion->data['activated_by']) ? 'Datos actualizados.' : 'Propuesta registrada. Pendiente de validación.');
    }

    /** Valores actuales en el formato del formulario (la fecha como Y-m-d, que es lo que manda el input). */
    private function valoresVigentes(Tarea $tarea): array
    {
        return [
            'nombre' => $tarea->nombre,
            'descripcion' => $tarea->descripcion,
            'fecha' => $tarea->fecha?->format('Y-m-d'),
            'porcentaje_avance' => (int) $tarea->porcentaje_avance,
        ];
    }

    /** Campos que ya tienen una propuesta pendiente: no se pueden volver a proponer. */
    private function camposConPropuesta(Collection $propuestas): array
    {
        return $propuestas
            ->flatMap(fn ($p) => array_keys($p->data['diff']['campos'] ?? []))
            ->unique()->values()->all();
    }

    /** Misma regla que Actualizacion::registrarCambioCampos(), para el texto y el botón. */
    private function estadoParaActualizacion(): int
    {
        return Actualizacion::estadoInicialParaCambio(Auth::user(), $this->estadoModelo);
    }

    public function render()
    {
        $tarea = Tarea::with(['estado'])->findOrFail($this->tareaId);
        $this->estadoModelo = $tarea->estado?->nombre ?? 'borrador';
        $propuestas = $this->propuestasDe($tarea, 'campos');

        return view('livewire.auditoria.tarea.show.ficha-tarea', [
            'tarea' => $tarea,
            'puedeActualizar' => Auth::user()->can('update', $tarea),
            'modo' => $this->modoCambio($tarea),
            'propuestas' => $propuestas,
            'bloqueados' => $this->camposConPropuesta($propuestas),
        ]);
    }
}
