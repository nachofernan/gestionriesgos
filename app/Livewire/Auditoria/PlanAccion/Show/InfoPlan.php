<?php

namespace App\Livewire\Auditoria\PlanAccion\Show;

use App\Models\Auditoria\PlanAccion;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel de "Información" de un Plan de Acción (avance, estado, vencimiento,
 * etc.). Antes era Blade estático dentro de planaccion/show.blade.php; se
 * extrae a su propio componente para que se refresque solo cuando
 * `GestionTareas` (hermano en la misma pantalla) sincroniza tareas, o cuando
 * `GestionActualizaciones` valida/aprueba un cambio — ver el evento
 * 'plan-actualizado' que ambos dispatchan, mismo mecanismo que InfoRiesgo.
 * `avance`/`vencimiento`/`esta_vencido` dependen de las tareas asociadas
 * (ver PlanAccion::getAvanceAttribute()), de ahí el eager-load de tareas.estado.
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
            ->findOrFail($this->planId);

        return view('livewire.auditoria.plan-accion.show.info-plan', [
            'planAccion' => $planAccion,
        ]);
    }
}
