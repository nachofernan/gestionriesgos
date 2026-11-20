<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\BuscaEnModal;
use App\Livewire\Auditoria\Riesgo\Show\Concerns\CambiaObjetivosDelRiesgo;
use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Gestión de los Objetivos asociados a un Riesgo (patrón $seleccionados en
 * memoria → guardar()). Un riesgo siempre debe conservar al menos un objetivo
 * asociado — quitar() y guardar() lo validan. Si el riesgo está en borrador,
 * sincroniza directo; si no, la asociación queda como una Actualizacion
 * (propuesta de cambio) que se aplica de inmediato sólo si el estado resultante
 * lo amerita (ver estadoParaActualizacion()). Los objetivos PEIS sólo entran si el
 * tipo del riesgo los admite (Riesgo::admitePeis(), D-054). Dispatcha 'riesgo-actualizado' al
 * persistir un cambio real, para que InfoRiesgo y GestionActualizaciones (bloques
 * hermanos en la misma pantalla) se refresquen solos.
 */
class GestionObjetivos extends Component
{
    use BuscaEnModal;
    use CambiaObjetivosDelRiesgo;
    use PropuestasEnBloque;

    public int $riesgoId;

    public bool $modalAbierto = false;

    public bool $editando = false;

    public bool $esBorrador = true;

    /** Gobierna la visibilidad de los botones de mutar (Editar / Proponer cambio) en la vista. */
    public bool $puedeActualizar = false;

    public string $estadoModelo = 'borrador';

    public string $error = '';

    /** @var array<int, array{id:int, nombre:string}> */
    public array $seleccionados = [];

    /**
     * IDs de objetivos asociados que no se listan: los "borrado" (rechazados,
     * quedan en limbo — ver CLAUDE.md) y los que el usuario actual no puede ver
     * (borrador/validado de un área que no gestiona). Se preservan en el pivot al
     * guardar para no detacharlos silenciosamente (mismo patrón que
     * GestionTareas::$ocultosIds).
     *
     * @var array<int, int>
     */
    public array $ocultosIds = [];

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
        $this->puedeActualizar = Auth::user()->can('proponer', $riesgo);
        $this->cargar();
    }

    /**
     * Otro bloque (o el historial) cambió el riesgo: se recarga lo vigente, salvo
     * que el usuario esté editando, para no pisarle lo que tiene a medio armar.
     */
    #[On('riesgo-actualizado')]
    public function refrescar(): void
    {
        if (! $this->editando) {
            $this->cargar();
        }
    }

    public function activarEdicion(): void
    {
        $this->editando = true;
        $this->error = '';
    }

    public function cancelarEdicion(): void
    {
        $this->cargar();
        $this->editando = false;
        $this->busqueda = '';
        $this->modalAbierto = false;
        $this->error = '';
    }

    public function abrirModal(): void
    {
        $this->reiniciarFiltrosModal();
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->reiniciarFiltrosModal();
        $this->modalAbierto = false;
    }

    public function agregar(int $objetivoId): void
    {
        if ($this->elementoConPropuesta('objetivos', $objetivoId)) {
            return;
        }

        if (collect($this->seleccionados)->contains('id', $objetivoId)) {
            return;
        }

        $objetivo = Objetivo::with(['estado', 'area'])->find($objetivoId);
        if (! $objetivo) {
            return;
        }

        if ($objetivo->es_peis && ! Riesgo::with('tipoRiesgo')->findOrFail($this->riesgoId)->admitePeis()) {
            $this->error = Riesgo::MENSAJE_PEIS_NO_ADMITIDO;

            return;
        }

        $this->seleccionados[] = [
            'id' => $objetivo->id,
            'nombre' => $objetivo->nombre,
            'descripcion' => $objetivo->descripcion,
            'estado' => $objetivo->estado?->nombre ?? 'borrador',
            'estado_color' => $objetivo->estado?->color ?? 'gray',
            'area' => $objetivo->area?->nombre,
            'fecha_objetivo' => $objetivo->fecha_objetivo?->format('d/m/Y'),
            'estrategico' => (bool) $objetivo->estrategico,
            'es_peis' => (bool) $objetivo->es_peis,
            'puede_ver' => Auth::user()->can('view', $objetivo),
            'url' => route('auditoria.objetivos.show', $objetivo->id),
        ];

        $this->error = '';
        $this->cerrarModal();
    }

    public function quitar(int $objetivoId): void
    {
        if ($this->elementoConPropuesta('objetivos', $objetivoId)) {
            return;
        }

        if (count($this->seleccionados) <= 1) {
            $this->error = 'El riesgo debe tener al menos un objetivo asociado.';

            return;
        }

        $this->seleccionados = array_values(
            array_filter($this->seleccionados, fn ($o) => $o['id'] !== $objetivoId)
        );
        $this->error = '';
    }

    /**
     * Si el riesgo está en borrador, sincroniza objetivos de inmediato. Si no,
     * arma el diff (agrega/quita) y lo guarda como Actualizacion; según el estado
     * que le toque (estadoParaActualizacion()) la aplica en el momento o la deja
     * pendiente de validación. Un PEIS en un riesgo que no lo admite corta acá
     * aunque haya llegado a $seleccionados (es una propiedad pública).
     * Tests: un_riesgo_que_no_es_de_corrupcion_no_puede_asociar_un_peis,
     * un_riesgo_de_corrupcion_asocia_un_objetivo_peis.
     */
    public function guardar(): void
    {
        if (empty($this->seleccionados)) {
            $this->error = 'Debe seleccionar al menos un objetivo.';

            return;
        }

        $riesgo = Riesgo::with(['objetivos.estado', 'tipoRiesgo'])->findOrFail($this->riesgoId);
        $this->authorize('proponer', $riesgo);
        // Los ocultos (borrado / no visibles) se re-agregan al sync para no detacharlos.
        $ids = array_values(array_unique(array_merge(
            collect($this->seleccionados)->pluck('id')->toArray(),
            $this->ocultosIds
        )));

        if (! $riesgo->admitePeis() && Objetivo::whereIn('id', $ids)->where('es_peis', true)->exists()) {
            $this->error = Riesgo::MENSAJE_PEIS_NO_ADMITIDO;

            return;
        }

        $diffRel = $this->construirDiff($riesgo);
        $resultado = $this->registrarCambioObjetivos($riesgo, $ids, $diffRel);

        if ($resultado === 'borrador') {
            if (! empty($diffRel)) {
                $this->dispatch('riesgo-actualizado');
            }
            $this->editando = false;
            $this->error = '';
            session()->flash('ok', 'Objetivos actualizados.');

            return;
        }

        $this->dispatch('riesgo-actualizado');
        $this->cancelarEdicion();
        session()->flash('ok', $resultado === 'directo' ? 'Objetivos actualizados.' : 'Propuesta registrada. Pendiente de validación.');
    }

    /**
     * Diff (agrega/quita) entre los objetivos actualmente persistidos en el riesgo
     * y la selección en memoria. La relación objetivo-riesgo no tiene mitigación, así
     * que sólo hay altas y bajas. Vacío si nada cambió. Lo consumen ambas ramas de
     * guardar() (borrador y propuesta) y lo pinta el historial de actualizaciones.
     * Compara sólo contra los objetivos vigentes (no ocultos, ver cargar()) para que
     * el diff no proponga "quitar" un oculto que en realidad se preserva vía $ids.
     */
    private function construirDiff(Riesgo $riesgo): array
    {
        $user = Auth::user();
        $antesItems = $riesgo->objetivos
            ->reject(fn ($o) => $o->estado?->nombre === 'borrado' || ! $user->can('view', $o))
            ->map(fn ($o) => ['id' => $o->id, 'nombre' => $o->nombre]);
        $antesIds = $antesItems->pluck('id');
        $despues = collect($this->seleccionados);

        return array_filter([
            'agrega' => $despues->filter(fn ($o) => ! $antesIds->contains($o['id']))
                ->map(fn ($o) => ['id' => $o['id'], 'nombre' => $o['nombre']])->values()->toArray(),
            'quita' => $antesItems->filter(fn ($o) => ! $despues->pluck('id')->contains($o['id']))->values()->toArray(),
        ], fn ($a) => ! empty($a));
    }

    private function cargar(): void
    {
        $riesgo = Riesgo::with(['objetivos.estado', 'objetivos.area', 'estado'])->findOrFail($this->riesgoId);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $this->areaModelo = Actualizacion::areaDe($riesgo);
        $this->esBorrador = $this->estadoModelo === 'borrador';

        $user = Auth::user();

        // Los objetivos "borrado" o no visibles para el usuario actual quedan fuera
        // de la lista pero se recuerdan para preservarlos en el pivot al guardar
        // (ver $ocultosIds y guardar()).
        $this->ocultosIds = $riesgo->objetivos
            ->filter(fn ($o) => $o->estado?->nombre === 'borrado' || ! $user->can('view', $o))
            ->pluck('id')->all();

        $this->seleccionados = $riesgo->objetivos
            ->reject(fn ($o) => $o->estado?->nombre === 'borrado' || ! $user->can('view', $o))
            ->sortBy(fn ($o) => Estado::peso($o->estado))
            ->map(fn ($o) => [
                'id' => $o->id,
                'nombre' => $o->nombre,
                'descripcion' => $o->descripcion,
                'estado' => $o->estado?->nombre ?? 'borrador',
                'estado_color' => $o->estado?->color ?? 'gray',
                'area' => $o->area?->nombre,
                'fecha_objetivo' => $o->fecha_objetivo?->format('d/m/Y'),
                'estrategico' => (bool) $o->estrategico,
                'es_peis' => (bool) $o->es_peis,
                'puede_ver' => true,
                'url' => route('auditoria.objetivos.show', $o->id),
            ])->values()->toArray();
    }

    public function render()
    {
        $riesgoVista = Riesgo::with(['objetivos.estado', 'tipoRiesgo'])->findOrFail($this->riesgoId);
        $propuestas = $this->propuestasDe($riesgoVista, 'objetivos');
        $bloqueados = $this->idsConPropuesta($propuestas, 'objetivos');
        $yaIds = collect($this->seleccionados)->pluck('id')->merge($bloqueados);

        $resultados = $this->modalAbierto
            ? Objetivo::query()
                ->with(['estado', 'area'])
                ->visiblePara(Auth::user())
                ->whereNot('estado_id', Estado::borrado()->id)
                ->when(! $riesgoVista->admitePeis(), fn ($q) => $q->where('objetivos.es_peis', false))
                ->tap(fn ($q) => $this->aplicarFiltrosModal($q, 'objetivos'))
                ->when($this->busqueda, fn ($q) => $q->where('objetivos.nombre', 'like', '%'.$this->busqueda.'%'))
                ->whereNotIn('objetivos.id', $yaIds)
                ->join('estados', 'estados.id', '=', 'objetivos.estado_id')
                ->select('objetivos.*')
                ->orderByRaw(Estado::ordenSql().' asc')
                ->orderBy('objetivos.nombre')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.auditoria.riesgo.show.gestion-objetivos', [
            'modo' => $this->modoCambio(),
            'bloqueados' => $bloqueados,
            'propuestas' => $propuestas,
            'marcas' => $this->marcasDe($propuestas, 'objetivos'),
            'diffEnCurso' => $this->editando ? $this->construirDiff($riesgoVista) : [],
            'resultados' => $resultados,
            ...$this->opcionesFiltrosModal(),
        ]);
    }
}
