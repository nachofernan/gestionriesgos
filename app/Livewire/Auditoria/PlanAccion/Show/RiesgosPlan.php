<?php

namespace App\Livewire\Auditoria\PlanAccion\Show;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bloque "Riesgos que mitiga" de planaccion/show: los riesgos asociados que el
 * usuario puede ver, con la mitigación que este plan aporta a cada uno y su total
 * → residual (accessors de Riesgo). Sólo lectura: la asociación se gestiona desde
 * cada riesgo. Se refresca con 'plan-actualizado' porque un cambio en las tareas
 * mueve el avance del plan y, al llegar al 100%, el residual de sus riesgos.
 */
class RiesgosPlan extends Component
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
        $plan = PlanAccion::with(['estado', 'tareas.estado'])->findOrFail($this->planId);

        $riesgos = $plan->riesgos()
            ->visiblePara(Auth::user())
            ->with(['estado', 'tipoRiesgo', 'area', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->get();

        return view('livewire.auditoria.plan-accion.show.riesgos-plan', [
            'riesgos' => Estado::ordenarColeccion($riesgos),
            'ocultos' => $plan->riesgos()->count() - $riesgos->count(),
            'mitiga' => $plan->estado?->nombre === 'aprobado' && $plan->estaCompleto(),
        ]);
    }
}
