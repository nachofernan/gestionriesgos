<?php

namespace App\Livewire\Auditoria\Modal;

use App\Models\Auditoria\Objetivo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de resumen rápido de un Objetivo, abierto vía el evento global
 * 'ver-objetivo' (ej. desde una fila de listado o un modal de otra entidad relacionada).
 */
class DetalleObjetivo extends Component
{
    public bool $abierto = false;

    public ?Objetivo $objetivo = null;

    /**
     * riesgos filtrados por visibilidad: sin esto, un usuario que llega al modal
     * viendo un objetivo asociado ve también riesgos en borrador de gerencias que
     * no le corresponden (axioma 1 + scopeVisiblePara).
     */
    #[On('ver-objetivo')]
    public function abrir(int $id): void
    {
        $this->objetivo = Objetivo::with([
            'estado', 'area', 'user',
            'riesgos' => fn ($q) => $q->visiblePara(Auth::user()),
            'riesgos.estado', 'riesgos.tipoRiesgo',
        ])->find($id);
        $this->abierto = (bool) $this->objetivo;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->objetivo = null;
    }

    public function render()
    {
        return view('livewire.auditoria.modal.detalle-objetivo');
    }
}
