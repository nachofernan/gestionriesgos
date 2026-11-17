<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de control/show: nombre y descripción, con las
 * propuestas pendientes sobre esos campos debajo. Mismo patrón que FichaRiesgo:
 * fuera de borrador se edita en el bloque y guardar() manda sólo lo que cambió a
 * Actualizacion::registrarCambioCampos(); en borrador se edita con el formulario
 * completo (ControlController::edit). El valor del control sale de su
 * cuestionario, que se contesta en ValorControl (D-051).
 */
class FichaControl extends Component
{
    use PropuestasEnBloque;

    public int $controlId;

    public string $estadoModelo = 'borrador';

    public bool $editando = false;

    /** @var array{nombre:string, descripcion:?string} */
    public array $form = [];

    /** Motivo del cambio: queda como mensaje de la Actualizacion. */
    public string $mensaje = '';

    private const CAMPOS = ['nombre', 'descripcion'];

    #[On('control-actualizado')]
    public function refrescar(): void {}

    public function mount(Control $control): void
    {
        $this->controlId = $control->id;
    }

    public function activarEdicion(): void
    {
        $control = Control::findOrFail($this->controlId);
        $this->authorize('proponer', $control);

        $this->form = $control->only(self::CAMPOS);
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
     * Test: la_ficha_del_control_devuelve_403_a_un_gerente_de_otra_gerencia.
     */
    public function guardar(): void
    {
        $control = Control::with('estado')->findOrFail($this->controlId);
        $this->authorize('proponer', $control);

        $this->validate([
            'mensaje' => 'required|string|min:3',
            'form.nombre' => 'required|string|max:255',
            'form.descripcion' => 'nullable|string',
        ], ['mensaje.required' => 'Contá brevemente por qué se cambia.']);

        $bloqueados = $this->camposConPropuesta($this->propuestasDe($control, 'campos'));
        $campos = [];
        foreach (self::CAMPOS as $campo) {
            $nuevo = $this->form[$campo] ?? null;
            if (in_array($campo, $bloqueados, true) || (string) $control->$campo === (string) $nuevo) {
                continue;
            }
            $campos[$campo] = $nuevo === '' ? null : $nuevo;
        }

        if (empty($campos)) {
            $this->addError('form', 'No hay cambios para guardar.');

            return;
        }

        $actualizacion = Actualizacion::registrarCambioCampos($control, Auth::user(), $this->mensaje, $campos);

        $this->dispatch('control-actualizado');
        $this->cancelarEdicion();
        session()->flash('ok', isset($actualizacion->data['activated_by']) ? 'Datos actualizados.' : 'Propuesta registrada. Pendiente de validación.');
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
        $control = Control::with(['area', 'user', 'estado'])->findOrFail($this->controlId);
        $this->estadoModelo = $control->estado?->nombre ?? 'borrador';
        $propuestas = $this->propuestasDe($control, 'campos');

        return view('livewire.auditoria.control.show.ficha-control', [
            'control' => $control,
            'puedeActualizar' => Auth::user()->can('proponer', $control),
            'modo' => $this->modoCambio(),
            'propuestas' => $propuestas,
            'bloqueados' => $this->camposConPropuesta($propuestas),
        ]);
    }
}
