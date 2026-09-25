<?php

namespace App\Livewire\Auditoria\PlanAccion\Show;

use App\Models\Auditoria\PlanAccion;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tarjeta lateral de planaccion/show: el avance (promedio de las tareas
 * aprobadas, PlanAccion::avance) como protagonista, si ya descuenta del residual
 * de sus riesgos (aprobado y al 100%), el vencimiento y quién lo gestiona. Se
 * refresca con 'plan-actualizado' (Ficha, Tareas o el historial aplicaron un
 * cambio), mismo mecanismo que InfoRiesgo.
 */
class InfoPlan extends Component
{
    public int $planId;

    public function mount(PlanAccion $plan): void
    {
        $this->planId = $plan->id;
    }

    #[On('plan-actualizado')]
    public function refrescar(): void {}

    public function render()
    {
        $planAccion = PlanAccion::with(['area', 'user', 'estado', 'tareas.estado'])
            ->withCount('riesgos')
            ->findOrFail($this->planId);

        return view('livewire.auditoria.plan-accion.show.info-plan', [
            'planAccion' => $planAccion,
        ]);
    }
}
