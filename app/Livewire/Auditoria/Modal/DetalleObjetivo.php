<?php

namespace App\Livewire\Auditoria\Modal;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Auditoria\Objetivo;

/**
 * Modal de resumen rápido de un Objetivo, abierto vía el evento global
 * 'ver-objetivo' (ej. desde una fila de listado o un modal de otra entidad relacionada).
 */
class DetalleObjetivo extends Component
{
    public bool $abierto = false;
    public ?Objetivo $objetivo = null;

    #[On('ver-objetivo')]
    public function abrir(int $id): void
    {
        $this->objetivo = Objetivo::with(['estado', 'area', 'user', 'riesgos.estado', 'riesgos.tipoRiesgo'])->find($id);
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
