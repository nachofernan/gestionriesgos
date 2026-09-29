<?php

namespace App\Livewire\Auditoria\Objetivo\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Objetivo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de objetivo/show: nombre, descripción y fecha objetivo, con las
 * propuestas pendientes sobre esos campos debajo. Mismo patrón que FichaRiesgo y
 * FichaControl: fuera de borrador se edita en el bloque y guardar() manda sólo lo
 * que cambió a Actualizacion::registrarCambioCampos(); en borrador se edita con
 * el formulario completo (ObjetivoController::edit), que es donde también se
 * cambian la clasificación estratégico/PEIS y sus ítems.
 */
class FichaObjetivo extends Component
{
    use PropuestasEnBloque;

    public int $objetivoId;

    public string $estadoModelo = 'borrador';

    public bool $editando = false;

    /** @var array{nombre:string, descripcion:?string, fecha_objetivo:?string} */
    public array $form = [];

    /** Motivo del cambio: queda como mensaje de la Actualizacion. */
    public string $mensaje = '';

    private const CAMPOS = ['nombre', 'descripcion', 'fecha_objetivo'];

    #[On('objetivo-actualizado')]
    public function refrescar(): void {}

    public function mount(Objetivo $objetivo): void
    {
        $this->objetivoId = $objetivo->id;
    }

    public function activarEdicion(): void
    {
        $objetivo = Objetivo::findOrFail($this->objetivoId);
        $this->authorize('proponer', $objetivo);

        $this->form = $this->valoresVigentes($objetivo);
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
     * Tests: la_ficha_del_objetivo_registra_solo_los_campos_que_cambiaron,
     * la_ficha_del_objetivo_devuelve_403_a_un_gerente_de_otra_gerencia.
     */
    public function guardar(): void
    {
        $objetivo = Objetivo::with('estado')->findOrFail($this->objetivoId);
        $this->authorize('proponer', $objetivo);

        $this->validate([
            'mensaje' => 'required|string|min:3',
            'form.nombre' => 'required|string|max:255',
            'form.descripcion' => 'nullable|string',
            'form.fecha_objetivo' => 'nullable|date',
        ], [
            'mensaje.required' => 'Contá brevemente por qué se cambia.',
            'form.fecha_objetivo.date' => 'Ingresá una fecha válida.',
        ]);

        $vigentes = $this->valoresVigentes($objetivo);
        $bloqueados = $this->camposConPropuesta($this->propuestasDe($objetivo, 'campos'));
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

        $actualizacion = Actualizacion::registrarCambioCampos($objetivo, Auth::user(), $this->mensaje, $campos);

        $this->dispatch('objetivo-actualizado');
        $this->cancelarEdicion();
        session()->flash('ok', isset($actualizacion->data['activated_by']) ? 'Datos actualizados.' : 'Propuesta registrada. Pendiente de validación.');
    }

    /** Valores actuales en el formato del formulario (la fecha como Y-m-d, que es lo que manda el input). */
    private function valoresVigentes(Objetivo $objetivo): array
    {
        return [
            'nombre' => $objetivo->nombre,
            'descripcion' => $objetivo->descripcion,
            'fecha_objetivo' => $objetivo->fecha_objetivo?->format('Y-m-d'),
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
        $objetivo = Objetivo::with(['estado'])->findOrFail($this->objetivoId);
        $this->estadoModelo = $objetivo->estado?->nombre ?? 'borrador';
        $propuestas = $this->propuestasDe($objetivo, 'campos');

        return view('livewire.auditoria.objetivo.show.ficha-objetivo', [
            'objetivo' => $objetivo,
            'puedeActualizar' => Auth::user()->can('proponer', $objetivo),
            'modo' => $this->modoCambio($objetivo),
            'propuestas' => $propuestas,
            'bloqueados' => $this->camposConPropuesta($propuestas),
        ]);
    }
}
