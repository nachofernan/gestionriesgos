<?php

namespace App\Livewire\Auditoria\Modal;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Auditoria\Riesgo;

/**
 * Modal de resumen rápido de un Riesgo, abierto vía el evento global 'ver-riesgo'
 * (ej. desde una fila de listado o un modal de otra entidad relacionada).
 */
class DetalleRiesgo extends Component
{
    public bool $abierto = false;
    public ?Riesgo $riesgo = null;

    #[On('ver-riesgo')]
    public function abrir(int $id): void
    {
        $this->riesgo = Riesgo::with([
            'estado',
            'tipoRiesgo',
            'area',
            'controles.estado',
            'objetivos.estado',
            'planesAccion.estado',
            'planesAccion.tareas.estado',
        ])->find($id);
        $this->abierto = (bool) $this->riesgo;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->riesgo = null;
    }

    public function render()
    {
        return view('livewire.auditoria.modal.detalle-riesgo');
    }
}
