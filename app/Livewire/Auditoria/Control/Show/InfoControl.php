<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Models\Auditoria\Control;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel de "Información" de un Control (mitigación por defecto, estado, área,
 * etc.). Antes era Blade estático dentro de control/show.blade.php; se extrae a
 * su propio componente para que se refresque solo cuando GestionActualizaciones
 * (el historial, en la misma pantalla) valida o aprueba un cambio — ver el
 * evento 'control-actualizado' que dispatcha, mismo mecanismo que InfoRiesgo.
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
        $control = Control::with(['area', 'user', 'estado'])->findOrFail($this->controlId);

        return view('livewire.auditoria.control.show.info-control', [
            'control' => $control,
        ]);
    }
}
