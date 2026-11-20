<?php

namespace App\Livewire\Auditoria\Objetivo\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\BuscaEnModal;
use App\Livewire\Auditoria\Riesgo\Show\Concerns\CambiaObjetivosDelRiesgo;
use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Bloque "Riesgos asociados" de objetivo/show, con alta y baja de riesgos (D-055).
 * Cada alta o baja es un cambio **del riesgo**, no del objetivo: pide `proponer`
 * sobre ese riesgo y sigue el mismo camino que el bloque Objetivos del riesgo
 * (CambiaObjetivosDelRiesgo). En borrador se asocia directo; si no, se aplica
 * en el acto o queda una propuesta en el historial del riesgo, con la elección
 * de rol de D-052/D-053. Como cada riesgo tiene su propio estado, las acciones
 * son de a una (no hay selección + guardar): agregar() / quitar() preparan el
 * riesgo destino y pedirGuardar() termina en guardar().
 * Un riesgo no se queda sin objetivos, y un PEIS sólo va en riesgos de Corrupción.
 * Tests: RiesgosObjetivoTest.
 */
class RiesgosObjetivo extends Component
{
    use BuscaEnModal;
    use CambiaObjetivosDelRiesgo;
    use PropuestasEnBloque;

    #[Locked]
    public int $objetivoId;

    /** Riesgo sobre el que se va a guardar la acción en curso (lo usa PropuestasEnBloque). */
    #[Locked]
    public ?int $riesgoId = null;

    /** 'agrega' | 'quita': la acción en curso sobre $riesgoId. */
    #[Locked]
    public ?string $operacion = null;

    /** Estado del riesgo destino: lo consumen modoCambio() y EligeRol. */
    public string $estadoModelo = 'borrador';

    public bool $modalAbierto = false;

    public string $error = '';

    public function mount(Objetivo $objetivo): void
    {
        $this->objetivoId = $objetivo->id;
    }

    public function abrirModal(): void
    {
        $this->reiniciarFiltrosModal();
        $this->error = '';
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->reiniciarFiltrosModal();
        $this->modalAbierto = false;
    }

    public function agregar(int $riesgoId): void
    {
        $this->preparar($riesgoId, 'agrega');
    }

    public function quitar(int $riesgoId): void
    {
        $this->preparar($riesgoId, 'quita');
    }

    /** Fija el riesgo destino y su estado/área, y sigue por pedirGuardar() (que puede abrir el modal de rol). */
    private function preparar(int $riesgoId, string $operacion): void
    {
        $riesgo = Riesgo::with('estado')->findOrFail($riesgoId);
        $this->authorize('proponer', $riesgo);

        $this->riesgoId = $riesgo->id;
        $this->operacion = $operacion;
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $this->areaModelo = Actualizacion::areaDe($riesgo);
        $this->error = '';

        $this->pedirGuardar();
    }

    /**
     * Aplica la acción preparada sobre el riesgo destino. Revalida todo del lado
     * servidor: permiso, que no tenga una propuesta pendiente con este objetivo, el
     * mínimo de un objetivo por riesgo y la regla PEIS (D-054).
     * Tests: un_objetivo_agrega_un_riesgo_en_borrador,
     * un_objetivo_propone_agregar_un_riesgo_validado,
     * no_se_asocia_un_riesgo_de_otra_gerencia_desde_el_objetivo,
     * no_se_quita_el_unico_objetivo_de_un_riesgo,
     * un_peis_solo_agrega_riesgos_de_corrupcion.
     */
    public function guardar(): void
    {
        if (! $this->riesgoId || ! in_array($this->operacion, ['agrega', 'quita'], true)) {
            return;
        }

        $objetivo = Objetivo::with('estado')->findOrFail($this->objetivoId);
        $riesgo = Riesgo::with(['estado', 'tipoRiesgo', 'objetivos.estado'])->findOrFail($this->riesgoId);
        $this->authorize('proponer', $riesgo);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $this->areaModelo = Actualizacion::areaDe($riesgo);

        if ($error = $this->motivoBloqueo($objetivo, $riesgo)) {
            $this->error = $error;
            $this->reiniciarAccion();

            return;
        }

        $actuales = $riesgo->objetivos->pluck('id');
        $ids = ($this->operacion === 'agrega' ? $actuales->push($objetivo->id) : $actuales->reject(fn ($id) => $id === $objetivo->id))
            ->unique()->values()->all();
        $diffRel = [$this->operacion => [['id' => $objetivo->id, 'nombre' => $objetivo->nombre]]];

        $resultado = $this->registrarCambioObjetivos($riesgo, $ids, $diffRel);
        $agrega = $this->operacion === 'agrega';
        $this->reiniciarAccion();
        $this->cerrarModal();

        if ($resultado === 'propuesta') {
            session()->flash('ok', 'Propuesta registrada en el riesgo. Pendiente de validación.');

            return;
        }

        // El cambio ya es vigente: se recarga la pantalla para que también se
        // actualicen los planes vinculados (salen de los riesgos del objetivo).
        session()->flash('ok', $agrega ? 'Riesgo asociado.' : 'Riesgo quitado.');
        $this->redirectRoute('auditoria.objetivos.show', $objetivo->id);
    }

    /** Por qué no se puede hacer la acción preparada (null si se puede). */
    private function motivoBloqueo(Objetivo $objetivo, Riesgo $riesgo): ?string
    {
        if ($objetivo->estado?->nombre === 'borrado' || $riesgo->estado?->nombre === 'borrado') {
            return 'No se pueden asociar elementos rechazados.';
        }

        if (in_array($riesgo->id, $this->riesgosConPropuesta($this->propuestasDelObjetivo()), true)) {
            return "El riesgo «{$riesgo->nombre}» ya tiene una propuesta pendiente con este objetivo.";
        }

        $asociado = $riesgo->objetivos->contains('id', $objetivo->id);

        if ($this->operacion === 'agrega') {
            if ($asociado) {
                return 'El riesgo ya está asociado a este objetivo.';
            }
            if ($objetivo->es_peis && ! $riesgo->admitePeis()) {
                return Riesgo::MENSAJE_PEIS_NO_ADMITIDO;
            }

            return null;
        }

        if (! $asociado) {
            return 'El riesgo no está asociado a este objetivo.';
        }
        $otrosVigentes = $riesgo->objetivos
            ->reject(fn ($o) => $o->id === $objetivo->id || $o->estado?->nombre === 'borrado');

        return $otrosVigentes->isEmpty()
            ? "No se puede quitar: es el único objetivo del riesgo «{$riesgo->nombre}», que necesita al menos uno."
            : null;
    }

    private function reiniciarAccion(): void
    {
        $this->riesgoId = null;
        $this->operacion = null;
        $this->estadoModelo = 'borrador';
        $this->areaModelo = null;
    }

    /**
     * Propuestas pendientes de los riesgos que agregan o quitan este objetivo.
     * Viven en el historial de cada riesgo; acá se listan sólo las de riesgos visibles.
     */
    private function propuestasDelObjetivo(): Collection
    {
        $user = Auth::user();

        return Actualizacion::query()
            ->where('actualizable_type', (new Riesgo)->getMorphClass())
            ->propuestasPendientes('objetivos')
            ->with(['actualizable.estado', 'user', 'estado'])
            ->latest('created_at')
            ->get()
            ->filter(fn ($p) => $p->actualizable && $user->can('view', $p->actualizable)
                && collect($p->data['diff']['relaciones']['objetivos'] ?? [])
                    ->flatten(1)->contains(fn ($item) => (int) ($item['id'] ?? 0) === $this->objetivoId))
            ->values();
    }

    /** @return array<int, int> */
    private function riesgosConPropuesta(Collection $propuestas): array
    {
        return $propuestas->pluck('actualizable_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function render()
    {
        $user = Auth::user();
        $objetivo = Objetivo::with('estado')->findOrFail($this->objetivoId);
        $editable = $objetivo->estado?->nombre !== 'borrado';

        $riesgos = Estado::ordenarColeccion($objetivo->riesgos()
            ->visiblePara($user)
            ->whereNot('riesgos.estado_id', Estado::borrado()->id)
            ->with(['estado', 'tipoRiesgo', 'area', 'objetivos.estado', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->get());

        $propuestas = $this->propuestasDelObjetivo();
        $conPropuesta = $this->riesgosConPropuesta($propuestas);

        $resultados = $this->modalAbierto && $editable
            ? Riesgo::query()
                ->with(['estado', 'area', 'tipoRiesgo'])
                ->visiblePara($user)
                ->whereNot('riesgos.estado_id', Estado::borrado()->id)
                ->tap(fn ($q) => $this->aplicarFiltrosModal($q, 'riesgos'))
                ->when($this->busqueda, fn ($q) => $q->where('riesgos.nombre', 'like', '%'.$this->busqueda.'%'))
                ->whereNotIn('riesgos.id', $riesgos->pluck('id')->merge($conPropuesta)->merge($objetivo->riesgos()->pluck('riesgos.id')))
                ->when($objetivo->es_peis, fn ($q) => $q->whereHas('tipoRiesgo', fn ($t) => $t->where('admite_peis', true)))
                ->join('estados', 'estados.id', '=', 'riesgos.estado_id')
                ->select('riesgos.*')
                ->orderByRaw(Estado::ordenSql().' asc')
                ->orderBy('riesgos.nombre')
                ->limit(40)
                ->get()
                // Sólo los riesgos sobre los que el usuario puede proponer: el cambio es del riesgo.
                ->filter(fn ($r) => $user->can('proponer', $r))
                ->take(20)
                ->values()
            : collect();

        return view('livewire.auditoria.objetivo.show.riesgos-objetivo', [
            'objetivo' => $objetivo,
            'editable' => $editable,
            'riesgos' => $riesgos,
            'puedeQuitar' => $riesgos->mapWithKeys(fn ($r) => [$r->id => $editable
                && $user->can('proponer', $r)
                && ! in_array($r->id, $conPropuesta, true)
                && $r->objetivos->contains(fn ($o) => $o->id !== $objetivo->id && $o->estado?->nombre !== 'borrado'),
            ]),
            'propuestas' => $propuestas,
            'resultados' => $resultados,
            ...$this->opcionesFiltrosModal(),
        ]);
    }
}
