<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Models\Auditoria\Control;
use App\Services\Auditoria\EvaluacionControl;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Card "Valor del control" de control/show (D-051): el puntaje del cuestionario,
 * lo que mitiga en cada riesgo, su clasificación y las respuestas vigentes. Abre
 * el cuestionario de config/control_preguntas.php para que lo conteste el área
 * (directo en borrador, propuesta fuera de borrador). Las reglas de quién,
 * cuándo y cómo se registra viven en Control::motivoBloqueoValoracion() y
 * registrarValoracion(); acá sólo se autoriza, se valida el borde y se refresca.
 */
class ValorControl extends Component
{
    public int $controlId;

    public bool $abierto = false;

    /** @var array{respuestas: array<string, int|string>, procedimiento: ?string, sistemas: array<int, string>, sistema_otro: ?string, frecuencia: ?string} */
    public array $cuestionario = [];

    public function mount(Control $control): void
    {
        $this->controlId = $control->id;
    }

    #[On('control-actualizado')]
    public function refrescar(): void {}

    /** Abre el cuestionario precargado con las respuestas vigentes (vacío si no tiene). */
    public function abrir(): void
    {
        $control = $this->control();
        $this->authorize('proponer', $control);
        if ($motivo = $control->motivoBloqueoValoracion(Auth::user())) {
            session()->flash('error', $motivo);

            return;
        }

        $this->cuestionario = $this->precarga($control->evaluacion);
        $this->resetValidation();
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->reset(['abierto', 'cuestionario']);
        $this->resetValidation();
    }

    /**
     * Valida las 7 respuestas con sus detalles y las registra por el circuito del
     * área (directo en borrador, propuesta fuera de borrador). Tests: ValorControlTest.
     */
    public function guardar(): void
    {
        $control = $this->control();
        $this->authorize('proponer', $control);
        if ($motivo = $control->motivoBloqueoValoracion(Auth::user())) {
            $this->addError('cuestionario', $motivo);

            return;
        }

        $this->validate(EvaluacionControl::reglas($this->cuestionario), EvaluacionControl::mensajes());

        $actualizacion = $control->registrarValoracion(Auth::user(), EvaluacionControl::desdeRespuestas($this->cuestionario));

        $this->cerrar();
        $this->dispatch('control-actualizado');
        session()->flash('ok', match (true) {
            $actualizacion === null => 'No hubo cambios en las respuestas.',
            ($actualizacion->data['tipo'] ?? null) === 'edicion', isset($actualizacion->data['activated_by']) => 'Valor del control actualizado.',
            default => 'Propuesta registrada. Pendiente de validación.',
        });
    }

    /** El formulario a partir de una evaluación guardada: las respuestas como vienen, sin respuesta si no hay. */
    private function precarga(?array $evaluacion): array
    {
        return [
            'respuestas' => collect(config('control_preguntas.preguntas'))
                ->mapWithKeys(fn ($p) => [$p['id'] => isset($evaluacion['respuestas'][$p['id']]) ? (int) $evaluacion['respuestas'][$p['id']] : ''])
                ->all(),
            'procedimiento' => $evaluacion['procedimiento'] ?? '',
            'sistemas' => $evaluacion['sistemas'] ?? [],
            'sistema_otro' => $evaluacion['sistema_otro'] ?? '',
            'frecuencia' => $evaluacion['frecuencia'] ?? '',
        ];
    }

    private function control(): Control
    {
        return Control::with('estado')->findOrFail($this->controlId);
    }

    public function render()
    {
        $control = $this->control();
        $user = Auth::user();

        // Sólo para mostrar el puntaje en vivo: lo que vale lo calcula el modelo al guardar.
        $enCurso = null;
        if ($this->abierto) {
            $respuestas = array_map(fn ($v) => (int) ($v == 1), $this->cuestionario['respuestas'] ?? []);
            $enCurso = EvaluacionControl::puntaje(['respuestas' => $respuestas]);
        }

        return view('livewire.auditoria.control.show.valor-control', [
            'control' => $control,
            'evaluacion' => $control->evaluacion,
            'operativo' => $control->evaluacion === null || EvaluacionControl::operativo($control->evaluacion),
            'pendiente' => $control->valoracionPendiente(),
            'motivo' => $user->can('proponer', $control) ? $control->motivoBloqueoValoracion($user) : false,
            'mitiga' => $control->mitiga(),
            'preguntas' => config('control_preguntas.preguntas'),
            'sistemas' => config('control_preguntas.sistemas'),
            'frecuencias' => config('control_preguntas.frecuencias'),
            'enCurso' => $enCurso,
        ]);
    }
}
