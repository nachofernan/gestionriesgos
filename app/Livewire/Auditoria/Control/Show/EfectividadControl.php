<?php

namespace App\Livewire\Auditoria\Control\Show;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bloque "Efectividad" de control/show (D-038): la efectividad vigente, el testeo
 * pendiente y la lista de testeos, con "Registrar testeo" (nuevo %, motivo y
 * archivos). Es la única vía para cambiar la efectividad: la regla vive en
 * Control::registrarTesteo(). Al guardar despacha 'control-actualizado' para que
 * se refresquen la ficha, InfoControl, los riesgos y la actividad.
 */
class EfectividadControl extends Component
{
    use WithFileUploads;

    public int $controlId;

    public bool $modalAbierto = false;

    public ?int $efectividad = null;

    public string $motivo = '';

    /** Adjuntos temporales de Livewire para el testeo que se está cargando. */
    public array $archivos = [];

    #[On('control-actualizado')]
    public function refrescar(): void {}

    public function mount(Control $control): void
    {
        $this->controlId = $control->id;
    }

    public function abrirModal(): void
    {
        $control = Control::with('estado')->findOrFail($this->controlId);
        $this->authorize($this->habilidad($control), $control);

        $this->efectividad = (int) $control->efectividad;
        $this->reset(['motivo', 'archivos']);
        $this->resetValidation();
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->reset(['motivo', 'archivos']);
        $this->resetValidation();
    }

    public function quitarArchivo(int $indice): void
    {
        unset($this->archivos[$indice]);
        $this->archivos = array_values($this->archivos);
    }

    /**
     * Registra el testeo. En borrador muta directo, así que exige 'update'; fuera
     * de borrador origina una propuesta y alcanza con 'proponer' (incluye al
     * auditor sobre lo validado/aprobado, D-020). Mientras haya un testeo con
     * cambio pendiente no se registra otro con cambio.
     * Tests: un_empleado_ajeno_no_puede_registrar_un_testeo,
     * un_auditor_no_testea_un_borrador_de_otra_gerencia,
     * no_se_registra_un_segundo_testeo_con_cambio_mientras_hay_uno_pendiente,
     * un_testeo_guarda_sus_adjuntos.
     */
    public function guardar(): void
    {
        $control = Control::with('estado')->findOrFail($this->controlId);
        $this->authorize($this->habilidad($control), $control);

        $this->validate([
            'efectividad' => 'required|integer|min:0|max:100',
            'motivo' => 'required|string|min:3',
            'archivos.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ], ['motivo.required' => 'Contá brevemente el resultado del testeo.']);

        if ((int) $this->efectividad !== (int) $control->efectividad && $control->testeoPendiente()) {
            $this->addError('efectividad', 'Ya hay un testeo con cambio esperando validación. Se puede registrar otro cuando se resuelva.');

            return;
        }

        $testeo = $control->registrarTesteo(Auth::user(), (int) $this->efectividad, $this->motivo);

        // Los medios van después de crear la actualización (mueven archivos en disco).
        foreach ($this->archivos as $archivo) {
            $testeo->addMedia($archivo->getRealPath())
                ->usingFileName($archivo->getClientOriginalName())
                ->toMediaCollection('adjuntos');
        }

        $aplicado = $testeo->estado_id === null
            || isset($testeo->data['activated_by'])
            || ($testeo->data['tipo'] ?? null) === 'edicion';

        $this->cerrarModal();
        $this->dispatch('control-actualizado');
        session()->flash('ok', $aplicado ? 'Testeo registrado.' : 'Testeo registrado. El cambio de efectividad queda pendiente de validación.');
    }

    private function habilidad(Control $control): string
    {
        return $control->estado?->nombre === 'borrador' ? 'update' : 'proponer';
    }

    public function render()
    {
        $control = Control::with('estado')->findOrFail($this->controlId);

        $testeos = $control->actualizaciones()
            ->where('data->origen', 'testeo')
            ->with(['user', 'estado', 'media'])
            ->get();

        return view('livewire.auditoria.control.show.efectividad-control', [
            'control' => $control,
            'testeos' => $testeos,
            'pendiente' => $testeos->first(fn ($t) => ($t->data['tipo'] ?? null) === 'cambio'
                && ! isset($t->data['activated_by'])
                && in_array($t->estado_id, [Estado::borrador()->id, Estado::validado()->id])),
            'puedeTestear' => Auth::user()->can($this->habilidad($control), $control),
        ]);
    }
}
