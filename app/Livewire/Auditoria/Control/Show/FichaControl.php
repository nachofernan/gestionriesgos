<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de control/show: nombre, descripción y mitigación por defecto, con las
 * propuestas pendientes sobre esos campos debajo. Mismo patrón que FichaRiesgo:
 * fuera de borrador se edita en el bloque y guardar() manda sólo lo que cambió a
 * Actualizacion::registrarCambioCampos(); en borrador se edita con el formulario
 * completo (ControlController::edit). La mitigación sale de los checks (D-040). Si
 * cambia, ofrece llevar el nuevo valor a todos los riesgos asociados ($propagar);
 * sin eso, sólo se recortan los que pasan el nuevo número.
 */
class FichaControl extends Component
{
    use PropuestasEnBloque;

    public int $controlId;

    public string $estadoModelo = 'borrador';

    public bool $editando = false;

    /** @var array{nombre:string, descripcion:?string, checks:array<int, string>} */
    public array $form = [];

    /** Motivo del cambio: queda como mensaje de la Actualizacion. */
    public string $mensaje = '';

    /** "Aplicar también a los riesgos asociados": ver Control::propagarMitigacionDefault(). */
    public bool $propagar = false;

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

        $this->form = $control->only(self::CAMPOS) + ['checks' => Control::normalizarChecks($control->checks)];
        $this->mensaje = '';
        $this->propagar = false;
        $this->resetValidation();
        $this->editando = true;
    }

    public function cancelarEdicion(): void
    {
        $this->editando = false;
        $this->reset(['form', 'mensaje', 'propagar']);
        $this->resetValidation();
    }

    /**
     * Valida en el borde, arma los campos que cambiaron (sin los que tienen una
     * propuesta pendiente) y los registra, pidiendo la propagación de la
     * mitigación si se tildó.
     * Tests: la_ficha_del_control_propaga_la_mitigacion_si_se_tilda,
     * la_ficha_del_control_devuelve_403_a_un_gerente_de_otra_gerencia.
     */
    public function guardar(): void
    {
        $control = Control::with('estado')->findOrFail($this->controlId);
        $this->authorize('proponer', $control);

        $this->validate([
            'mensaje' => 'required|string|min:3',
            'form.nombre' => 'required|string|max:255',
            'form.descripcion' => 'nullable|string',
            'form.checks' => 'array',
            'form.checks.*' => ['string', Rule::in(array_keys(Control::CHECKS))],
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

        // El número va junto con los checks para que el historial muestre los dos.
        $checks = Control::normalizarChecks($this->form['checks'] ?? []);
        if (! in_array('checks', $bloqueados, true) && $checks !== Control::normalizarChecks($control->checks)) {
            $campos['checks'] = $checks;
            $campos['mitigacion_default'] = Control::puntajeChecks($checks);
        }

        if (empty($campos)) {
            $this->addError('form', 'No hay cambios para guardar.');

            return;
        }

        $actualizacion = Actualizacion::registrarCambioCampos($control, Auth::user(), $this->mensaje, $campos, $this->propagar);

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

        // Vista previa de la propagación o el recorte: sólo los riesgos que el usuario
        // puede ver, el resto se cuenta. Se arma sólo si el número del form difiere del vigente.
        $afectados = collect();
        $ocultos = 0;
        $puntaje = Control::puntajeChecks($this->form['checks'] ?? []);
        if ($this->editando && $puntaje !== (int) $control->mitigacion_default) {
            $todos = $control->riesgos()->get();
            $visibles = $control->riesgos()->visiblePara(Auth::user())->pluck('riesgos.id');
            $afectados = $todos->filter(fn ($r) => $visibles->contains($r->id))
                ->map(fn ($r) => [
                    'nombre' => $r->nombre,
                    'mitigacion' => (int) ($r->pivot->mitigacion ?? $control->mitigacion_default),
                    // Sin número propio hereda el del control: no se recorta, sigue al nuevo.
                    'propia' => $r->pivot->mitigacion !== null,
                ])
                ->values();
            $ocultos = $todos->count() - $afectados->count();
        }

        return view('livewire.auditoria.control.show.ficha-control', [
            'control' => $control,
            'puedeActualizar' => Auth::user()->can('proponer', $control),
            'modo' => $this->modoCambio(),
            'propuestas' => $propuestas,
            'bloqueados' => $this->camposConPropuesta($propuestas),
            'afectados' => $afectados,
            'puntaje' => $puntaje,
            'ocultos' => $ocultos,
        ]);
    }
}
