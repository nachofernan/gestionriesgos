<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Models\Auditoria\Control;
use App\Services\Auditoria\EvaluacionControl;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Card "Valor del control" de control/show (D-051): el puntaje del cuestionario,
 * lo que mitiga en cada riesgo, su clasificación y las respuestas vigentes. Abre
 * el cuestionario de config/control_preguntas.php para que lo conteste el área
 * (directo en borrador, propuesta fuera de borrador) o para que el auditor
 * reevalúe en el acto un control aprobado, con comentario y adjuntos. Las reglas
 * de quién, cuándo y cómo se registra viven en Control::motivoBloqueoValoracion(),
 * registrarValoracion() y reevaluar(); acá sólo se autoriza, se valida el borde y
 * se refresca.
 */
class ValorControl extends Component
{
    use WithFileUploads;

    public int $controlId;

    public bool $abierto = false;

    /** 'area' (circuito de siempre) o 'auditor' (reevaluación en el acto). */
    public string $modo = 'area';

    /** Comentario obligatorio de la reevaluación del auditor. */
    public string $comentario = '';

    /** Adjuntos temporales de Livewire para la reevaluación. */
    public array $archivos = [];

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
        $this->modo = 'area';
        $this->resetValidation();
        $this->abierto = true;
    }

    /** Abre el cuestionario para la reevaluación del auditor (ControlPolicy::reevaluar). */
    public function abrirReevaluacion(): void
    {
        $control = $this->control();
        $this->authorize('reevaluar', $control);
        if ($control->valoracionPendiente()) {
            session()->flash('error', 'Hay una propuesta del área pendiente sobre el cuestionario: se reevalúa cuando se resuelva.');

            return;
        }

        $this->cuestionario = $this->precarga($control->evaluacion);
        $this->reset(['comentario', 'archivos']);
        $this->modo = 'auditor';
        $this->resetValidation();
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->reset(['abierto', 'cuestionario', 'modo', 'comentario', 'archivos']);
        $this->resetValidation();
    }

    public function quitarArchivo(int $indice): void
    {
        unset($this->archivos[$indice]);
        $this->archivos = array_values($this->archivos);
    }

    /**
     * Valida las 7 respuestas con sus detalles y las registra por el circuito del
     * área (directo en borrador, propuesta fuera de borrador). Tests: ValorControlTest.
     */
    public function guardar(): void
    {
        if ($this->modo === 'auditor') {
            $this->guardarReevaluacion();

            return;
        }

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

    /**
     * Reevaluación del auditor: valida el cuestionario, el comentario y los
     * adjuntos y la aplica en el acto (Control::reevaluar()). Los archivos van
     * a la Actualizacion que queda en el historial.
     * Tests: el_auditor_reevalua_un_control_aprobado_de_otra_area_en_el_acto,
     * la_reevaluacion_del_auditor_exige_comentario.
     */
    private function guardarReevaluacion(): void
    {
        $control = $this->control();
        $this->authorize('reevaluar', $control);
        if ($control->valoracionPendiente()) {
            $this->addError('cuestionario', 'Hay una propuesta del área pendiente sobre el cuestionario: se reevalúa cuando se resuelva.');

            return;
        }

        $this->validate(EvaluacionControl::reglas($this->cuestionario) + [
            'comentario' => 'required|string|min:3',
            'archivos.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ], EvaluacionControl::mensajes() + ['comentario.required' => 'Contá brevemente por qué cambia la valoración.']);

        $actualizacion = $control->reevaluar(Auth::user(), EvaluacionControl::desdeRespuestas($this->cuestionario), $this->comentario);

        // Los medios van después de crear la actualización (mueven archivos en disco).
        foreach ($actualizacion ? $this->archivos : [] as $archivo) {
            $actualizacion->addMedia($archivo->getRealPath())
                ->usingFileName($archivo->getClientOriginalName())
                ->toMediaCollection('adjuntos');
        }

        $this->cerrar();
        $this->dispatch('control-actualizado');
        session()->flash('ok', $actualizacion ? 'Reevaluación aplicada.' : 'No hubo cambios en las respuestas.');
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
            'puedeReevaluar' => $user->can('reevaluar', $control),
            'mitiga' => $control->mitiga(),
            'preguntas' => config('control_preguntas.preguntas'),
            'sistemas' => config('control_preguntas.sistemas'),
            'frecuencias' => config('control_preguntas.frecuencias'),
            'enCurso' => $enCurso,
        ]);
    }
}
