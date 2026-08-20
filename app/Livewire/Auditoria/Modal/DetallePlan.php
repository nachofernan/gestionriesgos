<?php

namespace App\Livewire\Auditoria\Modal;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de resumen rápido de un Plan de Acción, abierto vía el evento global
 * 'ver-plan' (ej. desde una fila de listado o un modal de otra entidad relacionada).
 */
class DetallePlan extends Component
{
    public bool $abierto = false;

    public ?PlanAccion $plan = null;

    /** riesgos filtrados por visibilidad (axioma 1 + scopeVisiblePara), ver DetalleObjetivo::abrir(). */
    #[On('ver-plan')]
    public function abrir(int $id): void
    {
        $this->plan = PlanAccion::with([
            'estado', 'area', 'user',
            'riesgos' => fn ($q) => $q->visiblePara(Auth::user()),
            'riesgos.estado', 'riesgos.tipoRiesgo',
            'tareas.estado', 'tareas.user',
        ])->find($id);

        if ($this->plan) {
            $this->plan->setRelation('riesgos', Estado::ordenarColeccion($this->plan->riesgos));
            $this->plan->setRelation('tareas', Estado::ordenarColeccion($this->plan->tareas));
        }

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
