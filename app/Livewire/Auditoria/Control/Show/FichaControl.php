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
 * Ficha de control/show: nombre, descripción y mitigación por defecto, con las
 * propuestas pendientes sobre esos campos debajo. Mismo patrón que FichaRiesgo:
 * fuera de borrador se edita en el bloque y guardar() manda sólo lo que cambió a
 * Actualizacion::registrarCambioCampos(); en borrador se edita con el formulario
 * completo (ControlController::edit). Si cambia la mitigación por defecto, ofrece
 * llevar el nuevo valor a todos los riesgos asociados ($propagar). También
 * propone pausar/reanudar el control (`pausado`: mientras está pausado no mitiga,
 * ver Control::mitiga()); es un campo más y sigue el mismo ciclo de propuesta.
 */
class FichaControl extends Component
{
    use PropuestasEnBloque;

    public int $controlId;

    public string $estadoModelo = 'borrador';

    public bool $editando = false;

    /** @var array{nombre:string, descripcion:?string, mitigacion_default:int, pausado:bool} */
    public array $form = [];

    /** Motivo del cambio: queda como mensaje de la Actualizacion. */
    public string $mensaje = '';

    /** "Aplicar también a los riesgos asociados": ver Control::propagarMitigacionDefault(). */
    public bool $propagar = false;

    private const CAMPOS = ['nombre', 'descripcion', 'mitigacion_default', 'pausado'];

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
            'form.mitigacion_default' => 'required|integer|min:1|max:10',
            'form.pausado' => 'boolean',
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

        // Vista previa de la propagación: sólo los riesgos que el usuario puede ver,
        // el resto se cuenta. Se arma sólo si el default del form difiere del vigente.
        $afectados = collect();
        $ocultos = 0;
        $nuevoDefault = $this->form['mitigacion_default'] ?? null;
        if ($this->editando && $nuevoDefault !== null && $nuevoDefault !== '' && (int) $nuevoDefault !== (int) $control->mitigacion_default) {
            $todos = $control->riesgos()->get();
            $visibles = $control->riesgos()->visiblePara(Auth::user())->pluck('riesgos.id');
            $afectados = $todos->filter(fn ($r) => $visibles->contains($r->id))
                ->map(fn ($r) => ['codigo' => $r->codigo, 'nombre' => $r->nombre, 'mitigacion' => (int) ($r->pivot->mitigacion ?? $control->mitigacion_default)])
                ->values();
            $ocultos = $todos->count() - $afectados->count();
        }

        return view('livewire.auditoria.control.show.ficha-control', [
            'control' => $control,
            'puedeActualizar' => Auth::user()->can('proponer', $control),
            'modo' => $this->modoCambio($control),
            'propuestas' => $propuestas,
            'bloqueados' => $this->camposConPropuesta($propuestas),
            'afectados' => $afectados,
            'ocultos' => $ocultos,
        ]);
    }
}
