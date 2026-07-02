<?php

namespace App\Livewire\Auditoria\Modal;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Auditoria\PlanAccion;

/**
 * Modal de resumen rápido de un Plan de Acción, abierto vía el evento global
 * 'ver-plan' (ej. desde una fila de listado o un modal de otra entidad relacionada).
 */
class DetallePlan extends Component
{
    public bool $abierto = false;
    public ?PlanAccion $plan = null;

    #[On('ver-plan')]
    public function abrir(int $id): void
    {
        $this->plan = PlanAccion::with(['estado', 'area', 'user', 'riesgos.estado', 'riesgos.tipoRiesgo', 'tareas.estado', 'tareas.user'])->find($id);
        $this->abierto = (bool) $this->plan;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->plan = null;
    }

    public function render()
    {
        return view('livewire.auditoria.modal.detalle-plan');
    }
}
