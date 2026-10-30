<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\Testeo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bloque "Evidencia de cumplimiento" de control/show (D-040): la efectividad de
 * cada relación con un riesgo, su último testeo y el historial de testeos. El
 * auditor registra un testeo por relación (fecha, nuevo %, comentario y archivos)
 * y se aplica en el acto (Testeo::registrar()). Al guardar despacha
 * 'control-actualizado' para que se refresquen los riesgos y la info del control.
 */
class EvidenciaControl extends Component
{
    use WithFileUploads;

    public int $controlId;

    /** Relación que se está testeando (null = modal cerrado). */
    public ?int $riesgoId = null;

    public ?int $efectividad = null;

    public string $fecha = '';

    public string $comentario = '';

    /** Adjuntos temporales de Livewire para el testeo que se está cargando. */
    public array $archivos = [];

    #[On('control-actualizado')]
    public function refrescar(): void {}

    public function mount(Control $control): void
    {
        $this->controlId = $control->id;
    }

    public function abrirTesteo(int $riesgoId): void
    {
        [$control, $riesgo] = $this->relacion($riesgoId);
        $this->authorize('testear', [$control, $riesgo]);

        $this->riesgoId = $riesgoId;
        $this->efectividad = (int) $riesgo->pivot->efectividad;
        $this->fecha = now()->toDateString();
        $this->reset(['comentario', 'archivos']);
        $this->resetValidation();
    }

    public function cerrarTesteo(): void
    {
        $this->reset(['riesgoId', 'efectividad', 'fecha', 'comentario', 'archivos']);
        $this->resetValidation();
    }

    public function quitarArchivo(int $indice): void
    {
        unset($this->archivos[$indice]);
        $this->archivos = array_values($this->archivos);
    }

    /**
     * Registra el testeo de la relación abierta. Autoriza ControlPolicy::testear()
     * con el riesgo, valida en el borde y deja los adjuntos en el testeo.
     * Tests: un_auditor_registra_un_testeo_y_se_aplica_en_el_acto,
     * nadie_mas_que_el_auditor_registra_un_testeo,
     * la_fecha_del_testeo_no_puede_ser_futura, un_testeo_guarda_sus_adjuntos.
     */
    public function guardar(): void
    {
        if ($this->riesgoId === null) {
            return;
        }

        [$control, $riesgo] = $this->relacion($this->riesgoId);
        $this->authorize('testear', [$control, $riesgo]);

        $this->validate([
            'efectividad' => 'required|integer|min:0|max:100',
            'fecha' => 'required|date|before_or_equal:today',
            'comentario' => 'required|string|min:3',
            'archivos.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ], [
            'comentario.required' => 'Contá brevemente el resultado del testeo.',
            'fecha.before_or_equal' => 'La fecha del testeo no puede ser futura.',
        ]);

        $testeo = Testeo::registrar($control, $riesgo, Auth::user(), (int) $this->efectividad, Carbon::parse($this->fecha), $this->comentario);

        // Los medios van después de crear el testeo (mueven archivos en disco).
        foreach ($this->archivos as $archivo) {
            $testeo->addMedia($archivo->getRealPath())
                ->usingFileName($archivo->getClientOriginalName())
                ->toMediaCollection('adjuntos');
        }

        $this->cerrarTesteo();
        $this->dispatch('control-actualizado');
        session()->flash('ok', 'Testeo registrado.');
    }

    /** El control y el riesgo de la relación, con el pivot y el estado cargados. */
    private function relacion(int $riesgoId): array
    {
        $control = Control::with('estado')->findOrFail($this->controlId);
        $riesgo = $control->riesgos()->with('estado')->findOrFail($riesgoId);

        return [$control, $riesgo];
    }

    public function render()
    {
        $control = Control::with('estado')->findOrFail($this->controlId);
        $user = Auth::user();

        $riesgos = $control->riesgos()->visiblePara($user)->with('estado')->orderBy('nombre')->get();
        $testeos = Testeo::where('control_id', $control->id)
            ->whereIn('riesgo_id', $riesgos->pluck('id'))
            ->with(['user', 'riesgo', 'media'])
            ->latest('fecha')->latest('id')
            ->get();

        return view('livewire.auditoria.control.show.evidencia-control', [
            'control' => $control,
            'riesgos' => $riesgos,
            'testeos' => $testeos,
            'ultimos' => $testeos->unique('riesgo_id')->keyBy('riesgo_id'),
            'puedeTestear' => $riesgos->mapWithKeys(fn ($r) => [$r->id => $user->can('testear', [$control, $r])])->all(),
            'enTesteo' => $this->riesgoId ? $riesgos->firstWhere('id', $this->riesgoId) : null,
        ]);
    }
}
