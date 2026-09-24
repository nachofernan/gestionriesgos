<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tarjeta de "Valor" de riesgo/show: impacto + probabilidad = total, menos lo
 * que descuentan controles y planes (accessors mitigacion_controles /
 * mitigacion_planes del modelo) = residual. Se refresca sola con
 * 'riesgo-actualizado' (lo emiten el resto de los bloques al persistir) y,
 * mientras se editan Controles o Planes, muestra el residual proyectado que
 * esos bloques emiten con 'residual-actualizado' (Alpine, sin tocar la DB).
 * Los datos descriptivos del riesgo viven en FichaRiesgo.
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
            'planesAccion.estado',
            'planesAccion.tareas.estado',
            'tipoRiesgo',
            'area',
            'user',
            'estado',
        ])->findOrFail($this->riesgoId);

        // Propuestas pendientes que moverían el valor (impacto/probabilidad), para avisarlo acá.
        $propuestasValor = $riesgo->actualizaciones()->propuestasPendientes('campos')->get()
            ->filter(fn ($p) => isset($p->data['diff']['campos']['impacto']) || isset($p->data['diff']['campos']['probabilidad']));

        return view('livewire.auditoria.riesgo.show.info-riesgo', [
            'riesgo' => $riesgo,
            'puedeActualizar' => Auth::user()->can('update', $riesgo),
            'propuestasValor' => $propuestasValor,
        ]);
    }
}
