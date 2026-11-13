<?php

namespace App\Livewire\Auditoria\PlanAccion\Show;

use App\Models\Auditoria\PlanAccion;
use App\Services\Auditoria\EvaluacionPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Card "Valor del plan" de planaccion/show (D-049): muestra cuánto se estima que
 * reduce el plan en probabilidad e impacto, la evaluación final si ya está, y lo
 * que descuenta hoy según su avance. Abre el cuestionario de
 * config/plan_preguntas.php para estimar (desde que existe el plan) o para la
 * evaluación final (plan aprobado al 100%). Las reglas de quién, cuándo y cómo
 * se registra viven en PlanAccion::motivoBloqueoValoracion() y
 * registrarValoracion(); acá sólo se autoriza, se valida el borde y se refresca.
 */
class ValorPlan extends Component
{
    public const CAMPOS = ['estimada' => 'evaluacion_estimada', 'final' => 'evaluacion_final'];

    public int $planId;

    /** 'estimada' o 'final' mientras el cuestionario está abierto; null si no. */
    public ?string $abierto = null;

    /** @var array{probabilidad: array<int,int|string>, impacto: array<int,int|string>} */
    public array $respuestas = [];

    public function mount(PlanAccion $plan): void
    {
        $this->planId = $plan->id;
    }

    #[On('plan-actualizado')]
    public function refrescar(): void {}

    /**
     * Abre el cuestionario precargado con las respuestas vigentes de ese campo
     * (la final arranca con las de la estimada).
     */
    public function abrir(string $tipo): void
    {
        $plan = $this->plan();
        $campo = self::CAMPOS[$tipo] ?? abort(404);
        $this->authorize('proponer', $plan);
        if ($motivo = $plan->motivoBloqueoValoracion(Auth::user(), $campo)) {
            session()->flash('error', $motivo);

            return;
        }

        $base = $plan->$campo ?? $plan->evaluacion_estimada;
        $this->respuestas = collect(config('plan_preguntas'))
            ->map(fn ($preguntas, $eje) => collect($preguntas)->mapWithKeys(fn ($p) => [$p['id'] => (int) ($base[$eje][$p['id']] ?? 0)])->all())
            ->all();
        $this->resetValidation();
        $this->abierto = $tipo;
    }

    public function cerrar(): void
    {
        $this->reset(['abierto', 'respuestas']);
        $this->resetValidation();
    }

    /**
     * Valida las 10 respuestas y las registra (directo en borrador, propuesta
     * fuera de borrador). Tests: ValorPlanTest.
     */
    public function guardar(): void
    {
        $plan = $this->plan();
        $campo = self::CAMPOS[$this->abierto] ?? abort(404);
        $this->authorize('proponer', $plan);
        if ($motivo = $plan->motivoBloqueoValoracion(Auth::user(), $campo)) {
            $this->addError('respuestas', $motivo);

            return;
        }

        $this->validate(EvaluacionPlan::reglas(), ['respuestas.*.*' => 'Respondé todas las preguntas.']);

        $actualizacion = $plan->registrarValoracion(Auth::user(), $campo, EvaluacionPlan::desdeRespuestas($this->respuestas));

        $this->cerrar();
        $this->dispatch('plan-actualizado');
        session()->flash('ok', match (true) {
            $actualizacion === null => 'No hubo cambios en las respuestas.',
            ($actualizacion->data['tipo'] ?? null) === 'edicion', isset($actualizacion->data['activated_by']) => 'Valor del plan actualizado.',
            default => 'Propuesta registrada. Pendiente de validación.',
        });
    }

    private function plan(): PlanAccion
    {
        return PlanAccion::with(['estado', 'tareas.estado'])->findOrFail($this->planId);
    }

    public function render()
    {
        $plan = $this->plan();
        $user = Auth::user();
        $puede = $user->can('proponer', $plan);

        $pendientes = $plan->actualizaciones()->propuestasPendientes('campos')->get()
            ->flatMap(fn ($a) => array_keys(array_intersect_key($a->data['campos'] ?? [], array_flip(self::CAMPOS))))
            ->unique()->values()->all();

        // Sólo para mostrar el total en vivo: el estado llega del cliente, así que no se asume completo.
        $enCurso = $this->abierto ? EvaluacionPlan::calcular(array_map(
            fn ($eje) => array_map(fn ($v) => (int) ($v == 1), (array) $eje),
            $this->respuestas
        )) : null;

        return view('livewire.auditoria.plan-accion.show.valor-plan', [
            'plan' => $plan,
            'estimada' => $plan->evaluacion_estimada ? EvaluacionPlan::calcular($plan->evaluacion_estimada) : null,
            'final' => $plan->evaluacion_final ? EvaluacionPlan::calcular($plan->evaluacion_final) : null,
            'efectiva' => $plan->reduccion_efectiva,
            'mitiga' => $plan->mitiga(),
            'pendientes' => $pendientes,
            'motivos' => $puede ? collect(self::CAMPOS)->map(fn ($campo) => $plan->motivoBloqueoValoracion($user, $campo))->all() : null,
            'preguntas' => config('plan_preguntas'),
            'enCurso' => $enCurso,
        ]);
    }
}
