<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Models\Auditoria\Control;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tarjeta lateral de control/show: la mitigación por defecto como protagonista,
 * en cuántos riesgos descuenta y quién lo gestiona. Se refresca con
 * 'control-actualizado' (FichaControl o GestionActualizaciones aplicaron un
 * cambio), mismo mecanismo que InfoRiesgo.
 */
class InfoControl extends Component
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
        $control = Control::with(['area', 'user', 'estado'])->withCount('riesgos')->findOrFail($this->controlId);

        return view('livewire.auditoria.control.show.info-control', [
            'control' => $control,
            'mitiga' => $control->mitiga(),
        ]);
    }
}
