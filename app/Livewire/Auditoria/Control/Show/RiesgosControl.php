<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bloque "Riesgos que mitiga" de control/show: los riesgos asociados que el
 * usuario puede ver, con lo que este control mitiga en cada uno y su total →
 * residual (accessors de Riesgo). Los checks de cada relación se editan acá
 * también (D-040): es otra puerta a la misma regla que GestionControles, con la
 * autorización sobre el riesgo. Alta y baja de la asociación siguen en el riesgo.
 * Se refresca con 'control-actualizado' porque cambiar el número del control
 * mueve los valores de acá.
 */
class RiesgosControl extends Component
{
    use PropuestasEnBloque;

    public int $controlId;

    /** Riesgo cuya relación se está editando, y sus checks en memoria. */
    public ?int $editandoRiesgoId = null;

    /** @var array<int, string> */
    public array $checksEnEdicion = [];

    public function mount(Control $control): void
    {
        $this->controlId = $control->id;
    }

    #[On('control-actualizado')]
    public function refrescar(): void {}

    public function editar(int $riesgoId): void
    {
        [$control, $riesgo] = $this->relacion($riesgoId);
        $this->authorize('proponer', $riesgo);

        if ($this->tienePropuesta($riesgo)) {
            return;
        }

        $pivot = $riesgo->pivot;
        $checks = $pivot->checks ?? ($pivot->mitigacion === null ? $control->checks : null);
        $this->checksEnEdicion = Control::normalizarChecks($checks);
        $this->editandoRiesgoId = $riesgoId;
    }

    public function alternarCheck(string $clave): void
    {
        if ($this->editandoRiesgoId === null || ! array_key_exists($clave, Control::CHECKS)) {
            return;
        }

        $this->checksEnEdicion = Control::normalizarChecks(in_array($clave, $this->checksEnEdicion, true)
            ? array_diff($this->checksEnEdicion, [$clave])
            : [...$this->checksEnEdicion, $clave]);
    }

    public function cancelar(): void
    {
        $this->reset(['editandoRiesgoId', 'checksEnEdicion']);
    }

    /**
     * Guarda los checks de la relación con el riesgo en edición. Autoriza
     * 'proponer' sobre el riesgo, no sobre el control: el número de la relación
     * lo decide quien gestiona el riesgo, desde donde lo toque. El circuito es
     * el de Actualizacion::registrarCambioPivot(): directo en borrador, en el acto
     * si el estado lo amerita, propuesta si no.
     * Tests: un_gerente_cambia_los_checks_de_una_relacion_desde_el_control,
     * un_gerente_de_otra_gerencia_no_cambia_los_checks_desde_el_control,
     * un_empleado_propone_los_checks_desde_el_control_y_se_aplican_al_validar.
     */
    public function guardar(): void
    {
        if ($this->editandoRiesgoId === null) {
            return;
        }

        [$control, $riesgo] = $this->relacion($this->editandoRiesgoId);
        $this->authorize('proponer', $riesgo);

        if ($this->tienePropuesta($riesgo)) {
            $this->addError('checks', 'Esta relación ya tiene un cambio esperando validación.');

            return;
        }

        $checks = Control::normalizarChecks($this->checksEnEdicion);
        $antes = (int) ($riesgo->pivot->mitigacion ?? $control->mitigacion_default);
        $despues = Control::numeroRelacion($checks, (int) $control->mitigacion_default);

        if ($checks === $riesgo->pivot->checks && $antes === $despues) {
            $this->cancelar();

            return;
        }

        Actualizacion::registrarCambioPivot(
            $riesgo,
            Auth::user(),
            "Mitigación de «{$control->nombre}»: {$antes} → {$despues}",
            'controles',
            $control->id,
            ['mitigacion' => $despues, 'checks' => $checks],
            ['id' => $control->id, 'nombre' => $control->nombre, 'mitigacion_antes' => $antes, 'mitigacion_despues' => $despues, 'checks' => $checks],
        );

        $this->cancelar();
        $this->dispatch('control-actualizado');
    }

    /** El control y el riesgo de la relación, con el pivot cargado. */
    private function relacion(int $riesgoId): array
    {
        $control = Control::findOrFail($this->controlId);
        $riesgo = $control->riesgos()->with('estado')->findOrFail($riesgoId);

        return [$control, $riesgo];
    }

    /** Un elemento no puede tener dos propuestas pendientes a la vez (ver PropuestasEnBloque::idsConPropuesta()). */
    private function tienePropuesta(Riesgo $riesgo): bool
    {
        return in_array($this->controlId, $this->idsConPropuesta($this->propuestasDe($riesgo, 'controles'), 'controles'), true);
    }

    public function render()
    {
        $control = Control::with('estado')->findOrFail($this->controlId);
        $user = Auth::user();

        $riesgos = $control->riesgos()
            ->visiblePara($user)
            ->with(['estado', 'tipoRiesgo', 'area', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->get();

        // Qué filas se pueden editar y cuáles esperan un cambio ya propuesto.
        $editables = [];
        $pendientes = [];
        foreach ($riesgos as $riesgo) {
            $pendiente = $this->tienePropuesta($riesgo);
            $pendientes[$riesgo->id] = $pendiente;
            $editables[$riesgo->id] = ! $pendiente && $riesgo->estado?->nombre !== 'borrado' && $user->can('proponer', $riesgo);
        }

        return view('livewire.auditoria.control.show.riesgos-control', [
            'control' => $control,
            'riesgos' => Estado::ordenarColeccion($riesgos),
            'ocultos' => $control->riesgos()->count() - $riesgos->count(),
            'mitiga' => $control->mitiga(),
            'editables' => $editables,
            'pendientes' => $pendientes,
        ]);
    }
}
