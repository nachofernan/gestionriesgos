<?php

namespace App\Livewire\Auditoria\Modal;

use App\Models\Auditoria\Tarea;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de resumen rápido de una Tarea, abierto vía el evento global 'ver-tarea'
 * (ej. desde una fila de listado o un modal de otra entidad relacionada).
 */
class DetalleTarea extends Component
{
    public bool $abierto = false;

    public ?Tarea $tarea = null;

    /**
     * planesAccion.riesgos filtrados por visibilidad: los riesgos cuelgan del plan,
     * no de la tarea (axioma 1 + scopeVisiblePara), ver DetalleObjetivo::abrir().
     */
    #[On('ver-tarea')]
    public function abrir(int $id): void
    {
        $this->tarea = Tarea::with([
            'estado', 'area', 'user', 'planesAccion.estado',
            'planesAccion.riesgos' => fn ($q) => $q->visiblePara(Auth::user()),
            'planesAccion.riesgos.estado', 'planesAccion.riesgos.tipoRiesgo',
        ])->find($id);
        $this->abierto = (bool) $this->tarea;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->tarea = null;
    }

    public function render()
    {
        return view('livewire.auditoria.modal.detalle-tarea');
    }
}
