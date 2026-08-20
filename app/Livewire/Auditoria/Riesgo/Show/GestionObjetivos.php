<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Gestión de los Objetivos asociados a un Riesgo (patrón $seleccionados en
 * memoria → guardar()). Un riesgo siempre debe conservar al menos un objetivo
 * asociado — quitar() y guardar() lo validan. Si el riesgo está en borrador,
 * sincroniza directo; si no, la asociación queda como una Actualizacion
 * (propuesta de cambio) que se aplica de inmediato sólo si el estado resultante
 * lo amerita (ver estadoParaActualizacion()).
 */
class GestionObjetivos extends Component
{
    public int $riesgoId;

    public bool $modalAbierto = false;

    public bool $editando = false;

    public bool $esBorrador = true;

    /** Gobierna la visibilidad de los botones de mutar (Editar / Proponer cambio) en la vista. */
    public bool $puedeActualizar = false;

    public string $estadoModelo = 'borrador';

    public string $busqueda = '';

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
        $this->puedeActualizar = Auth::user()->can('update', $riesgo);
        $this->cargar();
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
        $this->busqueda = '';
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->busqueda = '';
        $this->modalAbierto = false;
    }

    public function agregar(int $objetivoId): void
    {
        if (collect($this->seleccionados)->contains('id', $objetivoId)) {
            return;
        }

        $objetivo = Objetivo::with(['estado', 'area'])->find($objetivoId);
        if (! $objetivo) {
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
            'peis' => (bool) $objetivo->peis,
            'puede_ver' => Auth::user()->can('view', $objetivo),
            'url' => route('auditoria.objetivos.show', $objetivo->id),
        ];

        $this->error = '';
        $this->cerrarModal();
    }

    public function quitar(int $objetivoId): void
    {
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
     * pendiente de validación.
     */
    public function guardar(): void
    {
        if (empty($this->seleccionados)) {
            $this->error = 'Debe seleccionar al menos un objetivo.';

            return;
        }

        $riesgo = Riesgo::with('objetivos.estado')->findOrFail($this->riesgoId);
        $this->authorize('update', $riesgo);
        // Los ocultos (borrado / no visibles) se re-agregan al sync para no detacharlos.
        $ids = array_values(array_unique(array_merge(
            collect($this->seleccionados)->pluck('id')->toArray(),
            $this->ocultosIds
        )));

        $diffRel = $this->construirDiff($riesgo);

        if ($this->esBorrador) {
            $riesgo->objetivos()->sync($ids);

            // Dejar rastro del cambio en el historial también en borrador, pero sólo
            // si la selección realmente cambió (mismo diff que pinta la vista de
            // actualizaciones vía data['diff']['relaciones']).
            if (! empty($diffRel)) {
                $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Objetivos asociados',
                    'estado_id' => Estado::borrador()->id,
                    'data' => ['tipo' => 'edicion', 'diff' => ['relaciones' => ['objetivos' => $diffRel]]],
                ]);
            }

            $this->editando = false;
            $this->error = '';
            session()->flash('ok', 'Objetivos actualizados.');
        } else {
            // Riesgo compartido entre gerencias: el cambio no se aplica de una,
            // nace pendiente y el proponente vota a favor por su gerencia (ver
            // Riesgo::cambioRequiereDobleValidacion()).
            $dobleValidacion = $riesgo->cambioRequiereDobleValidacion(Auth::user());
            $estadoId = $dobleValidacion ? Estado::borrador()->id : $this->estadoParaActualizacion();

            $data = ['tipo' => 'cambio', 'relaciones' => ['objetivos' => ['sync' => $ids]]];
            if (! empty($diffRel)) {
                $data['diff'] = ['relaciones' => ['objetivos' => $diffRel]];
            }

            $aplicarAhora = ! $dobleValidacion && ($estadoId === Estado::aprobado()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado'));

            if ($aplicarAhora) {
                // El cambio se aplica en el acto: se marca activated_by para que el
                // historial lo rotule "Cambios aplicados" y no "Cambios propuestos".
                $data['activated_by'] = Auth::user()->name;
                $riesgo->objetivos()->sync($ids);
                $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Objetivos asociados actualizados',
                    'estado_id' => $estadoId,
                    'data' => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Objetivos actualizados.');
            } else {
                $actualizacion = $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Propuesta de cambio en objetivos asociados',
                    'estado_id' => $estadoId,
                    'data' => $data,
                ]);

                if ($dobleValidacion) {
                    $actualizacion->registrarVoto(Auth::user(), true);
                }

                $this->cancelarEdicion();
                session()->flash('ok', 'Propuesta registrada. Pendiente de validación.');
            }
        }
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

    /**
     * Regla de negocio: el comité editando un riesgo ya aprobado genera la
     * actualización directamente en aprobado; gerente o comité en cualquier otro
     * caso saltean el borrador y van a validado; el resto arranca en borrador.
     */
    private function estadoParaActualizacion(): int
    {
        $user = Auth::user();
        if ($this->estadoModelo === 'aprobado' && $user->esComite()) {
            return Estado::aprobado()->id;
        }
        if ($user->esGerente() || $user->esComite()) {
            return Estado::validado()->id;
        }

        return Estado::borrador()->id;
    }

    private function cargar(): void
    {
        $riesgo = Riesgo::with(['objetivos.estado', 'objetivos.area', 'estado'])->findOrFail($this->riesgoId);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
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
                'peis' => (bool) $o->peis,
                'puede_ver' => true,
                'url' => route('auditoria.objetivos.show', $o->id),
            ])->values()->toArray();
    }

    public function render()
    {
        $yaIds = collect($this->seleccionados)->pluck('id');

        $resultados = $this->modalAbierto
            ? Objetivo::query()
                ->with(['estado', 'area'])
                ->visiblePara(Auth::user())
                ->whereNot('estado_id', Estado::borrado()->id)
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
            'resultados' => $resultados,
        ]);
    }
}
