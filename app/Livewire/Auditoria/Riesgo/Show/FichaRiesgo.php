<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de riesgo/show: los datos propios del riesgo (descripción, respuesta,
 * fundamento, tipo, valoración) y, debajo, las propuestas pendientes sobre esos
 * campos. Fuera de borrador se edita en el mismo bloque, como el resto: el
 * formulario arranca con los valores vigentes, y al guardar se manda sólo lo que
 * cambió a Actualizacion::registrarCambioCampos() (que decide si se aplica o
 * queda como propuesta, con doble validación si corresponde). En borrador se
 * edita con el formulario completo (RiesgoController::edit). Un campo que ya
 * tiene una propuesta pendiente no se puede volver a proponer hasta resolverla.
 */
class FichaRiesgo extends Component
{
    use PropuestasEnBloque;

    public int $riesgoId;

    public string $estadoModelo = 'borrador';

    public bool $editando = false;

    /** @var array{nombre:string, descripcion:?string, respuesta:?string, fundamento:?string, tipo_riesgo_id:?int, impacto:int, probabilidad:int} */
    public array $form = [];

    /** Motivo del cambio: queda como mensaje de la Actualizacion. */
    public string $mensaje = '';

    private const CAMPOS = ['nombre', 'descripcion', 'respuesta', 'fundamento', 'tipo_riesgo_id', 'impacto', 'probabilidad'];

    #[On('riesgo-actualizado')]
    public function refrescar(): void {}

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
    }

    public function activarEdicion(): void
    {
        $riesgo = Riesgo::findOrFail($this->riesgoId);
        $this->authorize('update', $riesgo);

        $this->form = collect(self::CAMPOS)->mapWithKeys(function ($campo) use ($riesgo) {
            $valor = $riesgo->$campo;

            return [$campo => $valor instanceof \BackedEnum ? $valor->value : $valor];
        })->all();
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
     * Valida el formulario en el borde, arma los campos que realmente cambiaron
     * (dejando afuera los que tienen una propuesta pendiente) y los registra.
     * Tests: un_campo_con_propuesta_pendiente_no_se_vuelve_a_proponer,
     * la_ficha_devuelve_403_a_un_gerente_de_otra_gerencia.
     */
    public function guardar(): void
    {
        $riesgo = Riesgo::with('estado')->findOrFail($this->riesgoId);
        $this->authorize('update', $riesgo);

        $exigenFundamento = array_column(RespuestaRiesgo::exigenFundamento(), 'value');
        $this->validate([
            'mensaje' => 'required|string|min:3',
            'form.nombre' => 'required|string|max:255',
            'form.descripcion' => 'nullable|string',
            'form.tipo_riesgo_id' => 'required|exists:tipos_riesgo,id',
            'form.respuesta' => Riesgo::reglaRespuesta($this->form['tipo_riesgo_id'] ?? null),
            'form.fundamento' => ['nullable', 'string', 'required_if:form.respuesta,'.implode(',', $exigenFundamento)],
            'form.impacto' => 'required|integer|min:0|max:10',
            'form.probabilidad' => 'required|integer|min:0|max:10',
        ], [
            'mensaje.required' => 'Contá brevemente por qué se cambia.',
            'form.respuesta.not_in' => 'Un riesgo de este tipo no puede compartirse ni aceptarse como respuesta.',
            'form.fundamento.required_if' => 'Esta respuesta exige un fundamento.',
        ]);

        $bloqueados = $this->camposConPropuesta($this->propuestasDe($riesgo, 'campos'));
        $campos = [];
        foreach (self::CAMPOS as $campo) {
            $actual = $riesgo->$campo instanceof \BackedEnum ? $riesgo->$campo->value : $riesgo->$campo;
            $nuevo = $this->form[$campo] ?? null;
            if (in_array($campo, $bloqueados, true) || (string) $actual === (string) $nuevo) {
                continue;
            }
            $campos[$campo] = $nuevo === '' ? null : $nuevo;
        }

        if (empty($campos)) {
            $this->addError('form', 'No hay cambios para guardar.');

            return;
        }

        $actualizacion = Actualizacion::registrarCambioCampos($riesgo, Auth::user(), $this->mensaje, $campos);

        $this->dispatch('riesgo-actualizado');
        $this->cancelarEdicion();
        session()->flash('ok', isset($actualizacion->data['activated_by']) ? 'Datos actualizados.' : 'Propuesta registrada. Pendiente de validación.');
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
        $riesgo = Riesgo::with(['tipoRiesgo', 'area', 'user', 'estado', 'areas'])->findOrFail($this->riesgoId);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $propuestas = $this->propuestasDe($riesgo, 'campos');

        return view('livewire.auditoria.riesgo.show.ficha-riesgo', [
            'riesgo' => $riesgo,
            'puedeActualizar' => Auth::user()->can('update', $riesgo),
            'modo' => $this->modoCambio($riesgo),
            'gerencias' => $this->nombresGerencias($riesgo),
            'propuestas' => $propuestas,
            'bloqueados' => $this->camposConPropuesta($propuestas),
            'tiposRiesgo' => TipoRiesgo::orderBy('nombre')->pluck('nombre', 'id'),
            'respuestas' => collect(RespuestaRiesgo::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]),
        ]);
    }
}
