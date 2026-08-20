<?php

namespace App\Livewire\Auditoria\Modal;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de resumen rápido de un Control, abierto vía el evento global 'ver-control'
 * (ej. desde una fila de listado o un modal de otra entidad relacionada).
 */
class DetalleControl extends Component
{
    public bool $abierto = false;

    public ?Control $control = null;

    public ?int $mitigacion = null;

    /** riesgos filtrados por visibilidad (axioma 1 + scopeVisiblePara), ver DetalleObjetivo::abrir(). */
    #[On('ver-control')]
    public function abrir(int $id, ?int $mitigacion = null): void
    {
        $this->control = Control::with([
            'estado', 'area', 'user',
            'riesgos' => fn ($q) => $q->visiblePara(Auth::user()),
            'riesgos.estado', 'riesgos.tipoRiesgo',
        ])->find($id);

        if ($this->control) {
            $this->control->setRelation('riesgos', Estado::ordenarColeccion($this->control->riesgos));
        }

        $this->mitigacion = $mitigacion;
        $this->abierto = (bool) $this->control;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->control = null;
        $this->mitigacion = null;
    }

    public function render()
    {
        return view('livewire.auditoria.modal.detalle-control');
    }
}
