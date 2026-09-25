<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bloque "Riesgos que mitiga" de control/show: los riesgos asociados que el
 * usuario puede ver, con la mitigación de este control en cada uno y su total →
 * residual (accessors de Riesgo). Sólo lectura: la asociación se gestiona desde
 * cada riesgo. Se refresca con 'control-actualizado' porque cambiar la mitigación
 * por defecto con propagación mueve los valores de acá.
 */
class RiesgosControl extends Component
{
    public int $controlId;

    public function mount(Control $control): void
    {
        $this->controlId = $control->id;
    }

    #[On('control-actualizado')]
    public function refrescar(): void {}

    public function render()
    {
        $control = Control::with('estado')->findOrFail($this->controlId);

        $riesgos = $control->riesgos()
            ->visiblePara(Auth::user())
            ->with(['estado', 'tipoRiesgo', 'area', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->get();

        return view('livewire.auditoria.control.show.riesgos-control', [
            'control' => $control,
            'riesgos' => Estado::ordenarColeccion($riesgos),
            'ocultos' => $control->riesgos()->count() - $riesgos->count(),
            'mitiga' => $control->estado?->nombre === 'aprobado',
        ]);
    }
}
