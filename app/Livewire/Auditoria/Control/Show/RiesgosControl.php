<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Testeo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bloque "Riesgos que mitiga" de control/show: los riesgos asociados que el
 * usuario puede ver, con lo que este control mitiga en cada uno, su evidencia de
 * cumplimiento con el último testeo y su total → residual (accessors de Riesgo).
 * Dos cosas se editan por relación (D-040): los checks del número, con la misma
 * regla que GestionControles y la autorización sobre el riesgo, y el testeo del
 * auditor (Testeo::registrar()), con un modal para el historial de testeos. Alta
 * y baja de la asociación siguen en el riesgo. Se refresca con
 * 'control-actualizado' porque cambiar el número del control mueve los valores.
 */
class RiesgosControl extends Component
{
    use PropuestasEnBloque;
    use WithFileUploads;

    public int $controlId;

    /** Riesgo cuya relación se está editando, y sus checks en memoria. */
    public ?int $editandoRiesgoId = null;

    /** @var array<int, string> */
    public array $checksEnEdicion = [];

    /** Relación que se está testeando (null = modal cerrado) y los datos del testeo. */
    public ?int $testeoRiesgoId = null;

    public ?int $efectividad = null;

    public string $fecha = '';

    public string $comentario = '';

    /** Adjuntos temporales de Livewire para el testeo que se está cargando. */
    public array $archivos = [];

    /** Relación cuyo historial de testeos se muestra (null = modal cerrado). */
    public ?int $historialRiesgoId = null;

    public function mount(Control $control): void
    {
        $this->controlId = $control->id;
    }

    #[On('control-actualizado')]
    public function refrescar(): void {}

    public function editar(int $riesgoId): void
    {
        [$control, $riesgo] = $this->relacion($riesgoId);
        $this->authorize('proponer', $riesgo);

        if ($this->tienePropuesta($riesgo)) {
            return;
        }

        $pivot = $riesgo->pivot;
        $checks = $pivot->checks ?? ($pivot->mitigacion === null ? $control->checks : null);
        $this->checksEnEdicion = Control::normalizarChecks($checks);
        $this->editandoRiesgoId = $riesgoId;
    }

    public function alternarCheck(string $clave): void
    {
        if ($this->editandoRiesgoId === null || ! array_key_exists($clave, Control::CHECKS)) {
            return;
        }

        $this->checksEnEdicion = Control::normalizarChecks(in_array($clave, $this->checksEnEdicion, true)
            ? array_diff($this->checksEnEdicion, [$clave])
            : [...$this->checksEnEdicion, $clave]);
    }

    public function cancelar(): void
    {
        $this->reset(['editandoRiesgoId', 'checksEnEdicion']);
    }

    /**
     * Guarda los checks de la relación con el riesgo en edición. Autoriza
     * 'proponer' sobre el riesgo, no sobre el control: el número de la relación
     * lo decide quien gestiona el riesgo, desde donde lo toque. El circuito es
     * el de Actualizacion::registrarCambioPivot(): directo en borrador, en el acto
     * si el estado lo amerita, propuesta si no.
     * Tests: un_gerente_cambia_los_checks_de_una_relacion_desde_el_control,
     * un_gerente_de_otra_gerencia_no_cambia_los_checks_desde_el_control,
     * un_empleado_propone_los_checks_desde_el_control_y_se_aplican_al_validar.
     */
    public function guardar(): void
    {
        if ($this->editandoRiesgoId === null) {
            return;
        }

        [$control, $riesgo] = $this->relacion($this->editandoRiesgoId);
        $this->authorize('proponer', $riesgo);

        if ($this->tienePropuesta($riesgo)) {
            $this->addError('checks', 'Esta relación ya tiene un cambio esperando validación.');

            return;
        }

        $checks = Control::normalizarChecks($this->checksEnEdicion);
        $antes = (int) ($riesgo->pivot->mitigacion ?? $control->mitigacion_default);
        $despues = Control::numeroRelacion($checks, (int) $control->mitigacion_default);

        if ($checks === $riesgo->pivot->checks && $antes === $despues) {
            $this->cancelar();

            return;
        }

        Actualizacion::registrarCambioPivot(
            $riesgo,
            Auth::user(),
            "Mitigación de «{$control->nombre}»: {$antes} → {$despues}",
            'controles',
            $control->id,
            ['mitigacion' => $despues, 'checks' => $checks],
            ['id' => $control->id, 'nombre' => $control->nombre, 'mitigacion_antes' => $antes, 'mitigacion_despues' => $despues, 'checks' => $checks],
        );

        $this->cancelar();
        $this->dispatch('control-actualizado');
    }

    public function abrirTesteo(int $riesgoId): void
    {
        [$control, $riesgo] = $this->relacion($riesgoId);
        $this->authorize('testear', [$control, $riesgo]);

        $this->testeoRiesgoId = $riesgoId;
        $this->efectividad = (int) $riesgo->pivot->efectividad;
        $this->fecha = now()->toDateString();
        $this->reset(['comentario', 'archivos']);
        $this->resetValidation();
    }

    public function cerrarTesteo(): void
    {
        $this->reset(['testeoRiesgoId', 'efectividad', 'fecha', 'comentario', 'archivos']);
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
    public function guardarTesteo(): void
    {
        if ($this->testeoRiesgoId === null) {
            return;
        }

        [$control, $riesgo] = $this->relacion($this->testeoRiesgoId);
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

        $testeo = Testeo::registrar($control->load('estado'), $riesgo, Auth::user(), (int) $this->efectividad, Carbon::parse($this->fecha), $this->comentario);

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

    public function verTesteos(int $riesgoId): void
    {
        $this->relacion($riesgoId);
        $this->historialRiesgoId = $riesgoId;
    }

    public function cerrarTesteos(): void
    {
        $this->historialRiesgoId = null;
    }

    /** El control y el riesgo de la relación, con el pivot cargado. */
    private function relacion(int $riesgoId): array
    {
        $control = Control::with('estado')->findOrFail($this->controlId);
        $riesgo = $control->riesgos()->with('estado')->findOrFail($riesgoId);

        return [$control, $riesgo];
    }

    /** Un elemento no puede tener dos propuestas pendientes a la vez (ver PropuestasEnBloque::idsConPropuesta()). */
    private function tienePropuesta(Riesgo $riesgo): bool
    {
        return in_array($this->controlId, $this->idsConPropuesta($this->propuestasDe($riesgo, 'controles'), 'controles'), true);
    }

    public function render()
    {
        $control = Control::with('estado')->findOrFail($this->controlId);
        $user = Auth::user();

        $riesgos = $control->riesgos()
            ->visiblePara($user)
            ->with(['estado', 'tipoRiesgo', 'area', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->get();

        // Qué filas se pueden editar o testear y cuáles esperan un cambio ya propuesto.
        $editables = [];
        $pendientes = [];
        $puedeTestear = [];
        foreach ($riesgos as $riesgo) {
            $pendiente = $this->tienePropuesta($riesgo);
            $pendientes[$riesgo->id] = $pendiente;
            $editables[$riesgo->id] = ! $pendiente && $riesgo->estado?->nombre !== 'borrado' && $user->can('proponer', $riesgo);
            $puedeTestear[$riesgo->id] = $user->can('testear', [$control, $riesgo]);
        }

        // Testeos de las relaciones visibles: de entrada se muestra sólo el último de cada una.
        $testeos = Testeo::where('control_id', $control->id)
            ->whereIn('riesgo_id', $riesgos->pluck('id'))
            ->with(['user', 'media'])
            ->latest('fecha')->latest('id')
            ->get()
            ->groupBy('riesgo_id');

        return view('livewire.auditoria.control.show.riesgos-control', [
            'control' => $control,
            'riesgos' => Estado::ordenarColeccion($riesgos),
            'ocultos' => $control->riesgos()->count() - $riesgos->count(),
            'mitiga' => $control->mitiga(),
            'editables' => $editables,
            'pendientes' => $pendientes,
            'puedeTestear' => $puedeTestear,
            'testeos' => $testeos,
            'enTesteo' => $this->testeoRiesgoId ? $riesgos->firstWhere('id', $this->testeoRiesgoId) : null,
            'enHistorial' => $this->historialRiesgoId ? $riesgos->firstWhere('id', $this->historialRiesgoId) : null,
        ]);
    }
}
