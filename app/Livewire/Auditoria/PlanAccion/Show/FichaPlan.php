<?php

namespace App\Livewire\Auditoria\PlanAccion\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\PlanAccion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de planaccion/show: nombre y descripción del plan, con las propuestas
 * pendientes sobre esos campos debajo. Mismo patrón que FichaObjetivo: fuera de
 * borrador se edita en el bloque y guardar() manda sólo lo que cambió a
 * Actualizacion::registrarCambioCampos(); en borrador se edita con el formulario
 * completo (PlanAccionController::edit). El avance no es un campo del plan: sale
 * de sus tareas (GestionTareas).
 */
class FichaPlan extends Component
{
    use PropuestasEnBloque;

    public int $planId;

    public string $estadoModelo = 'borrador';

    public bool $editando = false;

    /** @var array{nombre:string, descripcion:?string} */
    public array $form = [];

    /** Motivo del cambio: queda como mensaje de la Actualizacion. */
    public string $mensaje = '';

    private const CAMPOS = ['nombre', 'descripcion'];

    #[On('plan-actualizado')]
    public function refrescar(): void {}

    public function mount(PlanAccion $plan): void
    {
        $this->planId = $plan->id;
    }

    public function activarEdicion(): void
    {
        $plan = PlanAccion::findOrFail($this->planId);
        $this->authorize('proponer', $plan);

        $this->form = $this->valoresVigentes($plan);
        $this->mensaje = '';
        $this->resetValidation();
        $this->editando = true;
    }

    public function cancelarEdicion(): void
    {
        $this->editando = false;
        $this->reset(['form', 'mensaje']);
        $this->resetValidation();
    }

    /**
     * Valida en el borde, arma los campos que cambiaron (sin los que tienen una
     * propuesta pendiente) y los registra.
     * Tests: la_ficha_del_plan_registra_solo_los_campos_que_cambiaron,
     * la_ficha_del_plan_devuelve_403_a_un_gerente_de_otra_gerencia.
     */
    public function guardar(): void
    {
        $plan = PlanAccion::with('estado')->findOrFail($this->planId);
        $this->authorize('proponer', $plan);

        $this->validate([
            'mensaje' => 'required|string|min:3',
            'form.nombre' => 'required|string|max:255',
            'form.descripcion' => 'nullable|string',
        ], [
            'mensaje.required' => 'Contá brevemente por qué se cambia.',
        ]);

        $vigentes = $this->valoresVigentes($plan);
        $bloqueados = $this->camposConPropuesta($this->propuestasDe($plan, 'campos'));
        $campos = [];
        foreach (self::CAMPOS as $campo) {
            $nuevo = $this->form[$campo] ?? null;
            $nuevo = $nuevo === '' ? null : $nuevo;
            if (in_array($campo, $bloqueados, true) || (string) $vigentes[$campo] === (string) $nuevo) {
                continue;
            }
            $campos[$campo] = $nuevo;
        }

        if (empty($campos)) {
            $this->addError('form', 'No hay cambios para guardar.');

            return;
        }

        $actualizacion = Actualizacion::registrarCambioCampos($plan, Auth::user(), $this->mensaje, $campos);

        $this->dispatch('plan-actualizado');
        $this->cancelarEdicion();
        session()->flash('ok', isset($actualizacion->data['activated_by']) ? 'Datos actualizados.' : 'Propuesta registrada. Pendiente de validación.');
    }

    /** Valores actuales en el formato del formulario. */
    private function valoresVigentes(PlanAccion $plan): array
    {
        return [
            'nombre' => $plan->nombre,
            'descripcion' => $plan->descripcion,
        ];
    }

    /** Campos que ya tienen una propuesta pendiente: no se pueden volver a proponer. */
    private function camposConPropuesta(Collection $propuestas): array
    {
        return $propuestas
            ->flatMap(fn ($p) => array_keys($p->data['diff']['campos'] ?? []))
            ->unique()->values()->all();
    }

    /** Misma regla que Actualizacion::registrarCambioCampos(), para el texto y el botón. */
    private function estadoParaActualizacion(): int
    {
        return Actualizacion::estadoInicialParaCambio(Auth::user(), $this->estadoModelo);
    }

    public function render()
    {
        $plan = PlanAccion::with(['estado'])->findOrFail($this->planId);
        $this->estadoModelo = $plan->estado?->nombre ?? 'borrador';
        $propuestas = $this->propuestasDe($plan, 'campos');

        return view('livewire.auditoria.plan-accion.show.ficha-plan', [
            'plan' => $plan,
            'puedeActualizar' => Auth::user()->can('proponer', $plan),
            'modo' => $this->modoCambio($plan),
            'propuestas' => $propuestas,
            'bloqueados' => $this->camposConPropuesta($propuestas),
        ]);
    }
}
