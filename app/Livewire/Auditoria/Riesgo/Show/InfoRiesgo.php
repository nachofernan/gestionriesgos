<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Models\Auditoria\Riesgo;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel de "Información" de un Riesgo (valor total, residual, impacto,
 * probabilidad, estado, criticidad, etc.). Antes era Blade estático dentro de
 * riesgo/show.blade.php; se extrae a su propio componente para poder
 * refrescarse solo cuando cualquier otro bloque de la pantalla (Áreas,
 * Objetivos, Controles, Planes, Actualizaciones) persiste un cambio sobre el
 * riesgo — ver el evento 'riesgo-actualizado' que esos componentes dispatchan.
 */
class InfoRiesgo extends Component
{
    public int $riesgoId;

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
    }

    #[On('riesgo-actualizado')]
    public function refrescar(): void
    {
        // Sin cuerpo: cualquier acción de un componente Livewire dispara su
        // render() de nuevo, así que sólo hace falta que el evento llegue acá.
    }

    public function render()
    {
        $riesgo = Riesgo::with([
            'controles.estado',
            'planesAccion.tareas.estado',
            'tipoRiesgo',
            'area',
            'user',
            'estado',
        ])->findOrFail($this->riesgoId);

        return view('livewire.auditoria.riesgo.show.info-riesgo', [
            'riesgo' => $riesgo,
        ]);
    }
}
