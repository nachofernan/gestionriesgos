<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Naturaleza;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Sede;
use App\Models\Auditoria\TipoRiesgo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ficha de riesgo/show: los datos propios del riesgo (descripción, respuesta,
 * fundamento, tipo, valoración, sedes, naturalezas) y, debajo, las propuestas pendientes
 * sobre ellos. Fuera de borrador se edita en el mismo bloque, como el resto: el
 * formulario arranca con los valores vigentes, y al guardar se manda sólo lo que
 * cambió a Actualizacion::registrarCambioCampos() (los campos) y a
 * Actualizacion::registrarCambioSync() (las sedes, y las naturalezas con su
 * fundamento, cada una como propuesta aparte); cada
 * una decide si se aplica o queda como propuesta. En borrador se edita con el
 * formulario completo (RiesgoController::edit). Lo que ya tiene una propuesta
 * pendiente no se puede volver a proponer hasta resolverla.
 */
class FichaRiesgo extends Component
{
    use PropuestasEnBloque;

    public int $riesgoId;

    public string $estadoModelo = 'borrador';

    public bool $editando = false;

    /** @var array{nombre:string, descripcion:?string, respuesta:?string, fundamento:?string, tipo_riesgo_id:?int, impacto:int, probabilidad:int} */
    public array $form = [];

    /** Ids de las sedes marcadas en el formulario (al menos una, D-029). */
    public array $sedes = [];

    /** Ids de las naturalezas marcadas (al menos una, D-032). */
    public array $naturalezas = [];

    /** Obligatorio con dos o más naturalezas; viaja en la propuesta de naturalezas. */
    public ?string $fundamentoNaturaleza = null;

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
        $this->authorize('proponer', $riesgo);

        $this->form = collect(self::CAMPOS)->mapWithKeys(function ($campo) use ($riesgo) {
            $valor = $riesgo->$campo;

            return [$campo => $valor instanceof \BackedEnum ? $valor->value : $valor];
        })->all();
        $this->sedes = $riesgo->sedes()->pluck('sedes.id')->map(fn ($id) => (string) $id)->all();
        $this->naturalezas = $riesgo->naturalezas()->pluck('naturalezas.id')->map(fn ($id) => (string) $id)->all();
        $this->fundamentoNaturaleza = $riesgo->fundamento_naturaleza;
        $this->mensaje = '';
        $this->resetValidation();
        $this->editando = true;
    }

    public function marcarTodasLasSedes(): void
    {
        $this->sedes = Sede::pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function cancelarEdicion(): void
    {
        $this->editando = false;
        $this->reset(['form', 'sedes', 'naturalezas', 'fundamentoNaturaleza', 'mensaje']);
        $this->resetValidation();
    }

    /**
     * Valida el formulario en el borde, arma los campos que realmente cambiaron
     * (dejando afuera los que tienen una propuesta pendiente) y los registra; si
     * cambiaron las sedes, las registra en una propuesta aparte. Lo mismo con las
     * naturalezas y su fundamento, que van juntos en una sola propuesta.
     * Tests: un_campo_con_propuesta_pendiente_no_se_vuelve_a_proponer,
     * la_ficha_devuelve_403_a_un_gerente_de_otra_gerencia,
     * con_una_propuesta_de_sedes_pendiente_no_se_vuelven_a_proponer,
     * un_empleado_propone_naturalezas_con_fundamento_y_se_aplican_juntos_al_validar.
     */
    public function guardar(): void
    {
        $riesgo = Riesgo::with(['estado', 'sedes', 'naturalezas'])->findOrFail($this->riesgoId);
        $this->authorize('proponer', $riesgo);

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
            'sedes' => 'required|array|min:1',
            'sedes.*' => 'integer|exists:sedes,id',
            'naturalezas' => 'required|array|min:1',
            'naturalezas.*' => 'integer|exists:naturalezas,id',
            'fundamentoNaturaleza' => Riesgo::reglaFundamentoNaturaleza($this->naturalezas),
        ], [
            'naturalezas.required' => 'Elegí al menos una naturaleza.',
            'naturalezas.min' => 'Elegí al menos una naturaleza.',
            'fundamentoNaturaleza.required' => 'Con más de una naturaleza, fundamentá por qué.',
            'sedes.required' => 'Elegí al menos una sede.',
            'sedes.min' => 'Elegí al menos una sede.',
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

        $sedeIds = array_map('intval', $this->sedes);
        $diffSedes = $this->propuestasDe($riesgo, 'sedes')->isEmpty()
            ? Sede::diff($riesgo->sedes->pluck('id')->all(), $sedeIds)
            : [];

        $naturalezaIds = array_map('intval', $this->naturalezas);
        $fundamentoNaturaleza = count($naturalezaIds) >= 2 && filled($this->fundamentoNaturaleza) ? $this->fundamentoNaturaleza : null;
        $diffNaturalezas = [];
        $cambiaFundamento = false;
        if ($this->propuestasDe($riesgo, 'naturalezas')->isEmpty()) {
            $diffNaturalezas = Naturaleza::diff($riesgo->naturalezas->pluck('id')->all(), $naturalezaIds);
            $cambiaFundamento = (string) $riesgo->fundamento_naturaleza !== (string) $fundamentoNaturaleza;
        }

        if (empty($campos) && empty($diffSedes) && empty($diffNaturalezas) && ! $cambiaFundamento) {
            $this->addError('form', 'No hay cambios para guardar.');

            return;
        }

        $registradas = collect();
        if (! empty($campos)) {
            $registradas->push(Actualizacion::registrarCambioCampos($riesgo, Auth::user(), $this->mensaje, $campos));
        }
        if (! empty($diffSedes)) {
            $registradas->push(Actualizacion::registrarCambioSync($riesgo, Auth::user(), $this->mensaje, 'sedes', $sedeIds, $diffSedes));
        }
        if (! empty($diffNaturalezas) || $cambiaFundamento) {
            $registradas->push(Actualizacion::registrarCambioSync(
                $riesgo, Auth::user(), $this->mensaje, 'naturalezas', $naturalezaIds, $diffNaturalezas,
                $cambiaFundamento ? ['fundamento_naturaleza' => $fundamentoNaturaleza] : [],
            ));
        }

        $this->dispatch('riesgo-actualizado');
        $this->cancelarEdicion();
        $aplicadas = $registradas->every(fn ($a) => isset($a->data['activated_by']));
        session()->flash('ok', $aplicadas ? 'Datos actualizados.' : 'Propuesta registrada. Pendiente de validación.');
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
        $riesgo = Riesgo::with(['tipoRiesgo', 'area', 'user', 'estado', 'sedes', 'naturalezas'])->findOrFail($this->riesgoId);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $propuestasCampos = $this->propuestasDe($riesgo, 'campos');
        $propuestasSedes = $this->propuestasDe($riesgo, 'sedes');
        $propuestasNaturalezas = $this->propuestasDe($riesgo, 'naturalezas');
        // La propuesta de naturalezas trae su fundamento en 'campos': se muestra una
        // sola vez, en su tarjeta de naturalezas.
        $propuestas = $propuestasCampos->reject(fn ($p) => isset($p->data['diff']['relaciones']));

        return view('livewire.auditoria.riesgo.show.ficha-riesgo', [
            'riesgo' => $riesgo,
            'todasLasSedes' => Sede::orderBy('id')->get(),
            'propuestasSedes' => $propuestasSedes,
            'sedesBloqueadas' => $propuestasSedes->isNotEmpty(),
            'todasLasNaturalezas' => Naturaleza::with('referencias')->orderBy('id')->get(),
            'propuestasNaturalezas' => $propuestasNaturalezas,
            'naturalezasBloqueadas' => $propuestasNaturalezas->isNotEmpty(),
            'puedeActualizar' => Auth::user()->can('proponer', $riesgo),
            'puedeRecalcular' => $riesgo->motivoBloqueoRecalculo(Auth::user()) === null,
            'modo' => $this->modoCambio(),
            'propuestas' => $propuestas,
            'bloqueados' => $this->camposConPropuesta($propuestas),
            'tiposRiesgo' => TipoRiesgo::orderBy('nombre')->pluck('nombre', 'id'),
            'respuestas' => collect(RespuestaRiesgo::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]),
        ]);
    }
}
