<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Livewire\Auditoria\Riesgo\Show\Concerns\PropuestasEnBloque;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Gestión de los Controles de mitigación asociados a un Riesgo, incluyendo el
 * valor de mitigación por control (patrón $seleccionados en memoria → guardar()).
 * Si el riesgo está en borrador, sincroniza directo; si no, la asociación queda
 * como una Actualizacion (propuesta de cambio) que se aplica de inmediato sólo si
 * el estado resultante lo amerita (ver estadoParaActualizacion()). Emite
 * 'residual-actualizado' cada vez que cambia la selección para que la vista
 * recalcule el valor residual del riesgo sin esperar a guardar (preview en
 * memoria, vía Alpine, no toca DB). Al persistir de verdad, además dispatcha
 * 'riesgo-actualizado' para que InfoRiesgo y GestionActualizaciones (bloques
 * hermanos) se refresquen con datos frescos — son dos eventos distintos a
 * propósito: uno es preview efímero, el otro es "esto ya se guardó".
 */
class GestionControles extends Component
{
    use PropuestasEnBloque;

    public int $riesgoId;

    public int $valorTotal = 0;

    public bool $modalAbierto = false;

    public bool $editando = false;

    public bool $esBorrador = true;

    /** Gobierna la visibilidad de los botones de mutar (Editar / Proponer cambio) en la vista. */
    public bool $puedeActualizar = false;

    public string $estadoModelo = 'borrador';

    public string $busqueda = '';

    /** Mitigación de los planes ya asociados que están al 100%, base fija del preview de residual. */
    public int $mitigacionPlanesBase = 0;

    /** @var array<int, array{id:int, nombre:string, mitigacion:int}> */
    public array $seleccionados = [];

    /**
     * IDs de controles asociados que no se listan: los "borrado" (rechazados,
     * quedan en limbo — ver CLAUDE.md) y los que el usuario actual no puede ver
     * (borrador/validado de un área que no gestiona). Se preservan en el pivot al
     * guardar para no detacharlos silenciosamente (mismo patrón que
     * GestionTareas::$ocultosIds).
     *
     * @var array<int, int>
     */
    public array $ocultosIds = [];

    /** snapshot para cancelar edición */
    private array $snapshot = [];

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
        $this->valorTotal = $riesgo->valor_total;
        $this->puedeActualizar = Auth::user()->can('update', $riesgo);
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
    }

    public function cancelarEdicion(): void
    {
        $this->cargar();
        $this->editando = false;
        $this->busqueda = '';
        $this->modalAbierto = false;
        $this->dispatch('residual-actualizado', valor: $this->residualActual());
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

    public function actualizarMitigacion(int $controlId, int $valor): void
    {
        if ($this->elementoConPropuesta('controles', $controlId)) {
            return;
        }

        foreach ($this->seleccionados as &$item) {
            if ($item['id'] === $controlId) {
                $item['mitigacion'] = max(0, min(10, $valor));
                break;
            }
        }
        $this->dispatch('residual-actualizado', valor: $this->residualActual());
    }

    public function agregar(int $controlId): void
    {
        if ($this->elementoConPropuesta('controles', $controlId)) {
            return;
        }

        if (collect($this->seleccionados)->contains('id', $controlId)) {
            return;
        }

        $control = Control::with(['estado', 'area'])->find($controlId);
        if (! $control) {
            return;
        }

        $this->seleccionados[] = [
            'id' => $control->id,
            'nombre' => $control->nombre,
            'descripcion' => $control->descripcion,
            'mitigacion' => $control->mitigacion_default,
            'mitigacion_default' => $control->mitigacion_default,
            'estado' => $control->estado?->nombre ?? 'borrador',
            'estado_color' => $control->estado?->color ?? 'gray',
            'area' => $control->area?->nombre,
            'puede_ver' => Auth::user()->can('view', $control),
            'url' => route('auditoria.controles.show', $control->id),
        ];

        $this->cerrarModal();
        $this->dispatch('residual-actualizado', valor: $this->residualActual());
    }

    public function quitar(int $controlId): void
    {
        if ($this->elementoConPropuesta('controles', $controlId)) {
            return;
        }

        $this->seleccionados = array_values(
            array_filter($this->seleccionados, fn ($c) => $c['id'] !== $controlId)
        );
        $this->dispatch('residual-actualizado', valor: $this->residualActual());
    }

    /**
     * Si el riesgo está en borrador, sincroniza controles y mitigaciones de
     * inmediato. Si no, arma el diff (agrega/quita/cambia mitigación) y lo guarda
     * como Actualizacion; según el estado que le toque (estadoParaActualizacion())
     * la aplica en el momento o la deja pendiente de validación.
     */
    public function guardar(): void
    {
        $riesgo = Riesgo::with('controles.estado')->findOrFail($this->riesgoId);
        $this->authorize('update', $riesgo);

        $sync = [];
        foreach ($this->seleccionados as $item) {
            $sync[(string) $item['id']] = ['mitigacion' => $item['mitigacion']];
        }
        // Los ocultos (borrado / no visibles) se re-agregan al sync (mitigación
        // persistida) para no detacharlos.
        foreach ($this->ocultosIds as $id) {
            $antes = $riesgo->controles->firstWhere('id', $id);
            $sync[(string) $id] = ['mitigacion' => $antes?->pivot->mitigacion];
        }

        $diffRel = $this->construirDiff($riesgo);

        if ($this->esBorrador) {
            $riesgo->controles()->sync($sync);

            // Dejar rastro del cambio en el historial también en borrador, pero sólo
            // si la selección realmente cambió (mismo diff que pinta la vista de
            // actualizaciones vía data['diff']['relaciones']).
            if (! empty($diffRel)) {
                $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Controles de mitigación asociados',
                    'estado_id' => Estado::borrador()->id,
                    'data' => ['tipo' => 'edicion', 'diff' => ['relaciones' => ['controles' => $diffRel]]],
                ]);
                $this->dispatch('riesgo-actualizado');
            }

            $this->editando = false;
            $this->dispatch('residual-actualizado', valor: $this->residualActual());
            session()->flash('ok', 'Controles actualizados.');
        } else {
            // Riesgo compartido entre gerencias: el cambio no se aplica de una,
            // nace pendiente y el proponente vota a favor por su gerencia (ver
            // Riesgo::cambioRequiereDobleValidacion()).
            $modo = $this->modoCambio($riesgo);
            $dobleValidacion = $modo === 'doble';
            $estadoId = $dobleValidacion ? Estado::borrador()->id : $this->estadoParaActualizacion();

            $data = ['tipo' => 'cambio', 'relaciones' => ['controles' => ['sync' => $sync]]];
            if (! empty($diffRel)) {
                $data['diff'] = ['relaciones' => ['controles' => $diffRel]];
            }

            $aplicarAhora = $modo === 'directo';

            if ($aplicarAhora) {
                // El cambio se aplica en el acto: se marca activated_by para que el
                // historial lo rotule "Cambios aplicados" y no "Cambios propuestos".
                $data['activated_by'] = Auth::user()->name;
                $riesgo->controles()->sync($sync);
                $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Controles de mitigación actualizados',
                    'estado_id' => $estadoId,
                    'data' => $data,
                ]);
                $this->dispatch('riesgo-actualizado');
                $this->cancelarEdicion();
                session()->flash('ok', 'Controles actualizados.');
            } else {
                // Fuera del modo directo, cada alta/baja/cambio es su propia propuesta.
                $this->proponerPorElemento($riesgo, 'controles', $diffRel, $estadoId, $dobleValidacion, 'control');

                $this->dispatch('riesgo-actualizado');
                $this->cancelarEdicion();
                session()->flash('ok', 'Propuesta registrada. Pendiente de validación.');
            }
        }
    }

    /**
     * Diff (agrega/quita/cambia mitigación) entre los controles actualmente
     * persistidos en el riesgo y la selección en memoria. Vacío si nada cambió.
     * Lo consumen ambas ramas de guardar() (borrador y propuesta de cambio) y lo
     * pinta el historial de actualizaciones vía data['diff']['relaciones'].
     * Compara sólo contra los controles vigentes (no ocultos, ver cargar()) para
     * que el diff no proponga "quitar" un oculto que en realidad se preserva.
     */
    private function construirDiff(Riesgo $riesgo): array
    {
        $user = Auth::user();
        $antesMap = $riesgo->controles
            ->reject(fn ($c) => $c->estado?->nombre === 'borrado' || ! $user->can('view', $c))
            // Mismo valor efectivo que muestra cargar() y descuenta valor_residual: sin el
            // fallback, un pivot en null se leía como "cambió a N" aunque nadie lo tocara.
            ->mapWithKeys(fn ($c) => [$c->id => ['nombre' => $c->nombre, 'mitigacion' => (int) ($c->pivot->mitigacion ?? $c->mitigacion_default)]]);
        $antesIds = $antesMap->keys();
        $despuesIds = collect($this->seleccionados)->pluck('id');

        return array_filter([
            'agrega' => collect($this->seleccionados)
                ->filter(fn ($c) => ! $antesIds->contains($c['id']))
                ->map(fn ($c) => ['id' => $c['id'], 'nombre' => $c['nombre'], 'mitigacion' => $c['mitigacion']])
                ->values()->toArray(),
            'quita' => $antesMap->filter(fn ($v, $k) => ! $despuesIds->contains($k))
                ->map(fn ($v, $k) => ['id' => $k, 'nombre' => $v['nombre']])
                ->values()->toArray(),
            'cambia' => collect($this->seleccionados)
                ->filter(fn ($c) => $antesIds->contains($c['id']) && $antesMap[$c['id']]['mitigacion'] !== (int) $c['mitigacion'])
                ->map(fn ($c) => ['id' => $c['id'], 'nombre' => $c['nombre'], 'mitigacion_antes' => $antesMap[$c['id']]['mitigacion'], 'mitigacion_despues' => $c['mitigacion']])
                ->values()->toArray(),
        ], fn ($a) => ! empty($a));
    }

    /**
     * Residual = valor total del riesgo menos la mitigación de los controles
     * seleccionados que estén en estado "aprobado" menos la ya persistida de los
     * planes al 100% (no persiste, es sólo para feedback en vivo). Refleja la misma
     * regla que Riesgo::getValorResidualAttribute: un control no aprobado no mitiga.
     */
    private function residualActual(): int
    {
        $mitigacion = collect($this->seleccionados)
            ->filter(fn ($c) => ($c['estado'] ?? null) === 'aprobado')
            ->sum('mitigacion');

        return max(0, $this->valorTotal - $mitigacion - $this->mitigacionPlanesBase);
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
        $riesgo = Riesgo::with(['controles.estado', 'controles.area', 'planesAccion.estado', 'planesAccion.tareas.estado', 'estado'])->findOrFail($this->riesgoId);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $this->valorTotal = $riesgo->valor_total;
        $this->esBorrador = $this->estadoModelo === 'borrador';

        // Sólo los planes aprobados y al 100% mitigan (misma regla que el accessor valor_residual).
        $this->mitigacionPlanesBase = (int) $riesgo->planesAccion->sum(function ($p) {
            $aporta = $p->estado?->nombre === 'aprobado' && $p->estaCompleto();

            return $aporta ? ($p->pivot->mitigacion ?? 0) : 0;
        });

        $user = Auth::user();

        // Los controles "borrado" o no visibles para el usuario actual quedan fuera
        // de la lista pero se recuerdan para preservarlos en el pivot al guardar
        // (ver $ocultosIds y guardar()).
        $this->ocultosIds = $riesgo->controles
            ->filter(fn ($c) => $c->estado?->nombre === 'borrado' || ! $user->can('view', $c))
            ->pluck('id')->all();

        $this->seleccionados = $riesgo->controles
            ->reject(fn ($c) => $c->estado?->nombre === 'borrado' || ! $user->can('view', $c))
            ->sortBy(fn ($c) => Estado::peso($c->estado))
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'descripcion' => $c->descripcion,
                'mitigacion' => $c->pivot->mitigacion ?? $c->mitigacion_default,
                'mitigacion_default' => $c->mitigacion_default,
                'estado' => $c->estado?->nombre ?? 'borrador',
                'estado_color' => $c->estado?->color ?? 'gray',
                'area' => $c->area?->nombre,
                'puede_ver' => true,
                'url' => route('auditoria.controles.show', $c->id),
            ])->values()->toArray();
    }

    public function render()
    {
        $riesgoVista = Riesgo::with(['areas', 'controles.estado'])->findOrFail($this->riesgoId);
        $propuestas = $this->propuestasDe($riesgoVista, 'controles');
        $bloqueados = $this->idsConPropuesta($propuestas, 'controles');
        $yaIds = collect($this->seleccionados)->pluck('id')->merge($bloqueados);

        $resultados = $this->modalAbierto
            ? Control::query()
                ->with(['estado', 'area'])
                ->visiblePara(Auth::user())
                ->whereNot('estado_id', Estado::borrado()->id)
                ->when($this->busqueda, fn ($q) => $q->where('controles.nombre', 'like', '%'.$this->busqueda.'%'))
                ->whereNotIn('controles.id', $yaIds)
                ->join('estados', 'estados.id', '=', 'controles.estado_id')
                ->select('controles.*')
                ->orderByRaw(Estado::ordenSql().' asc')
                ->orderBy('controles.nombre')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.auditoria.riesgo.show.gestion-controles', [
            'modo' => $this->modoCambio($riesgoVista),
            'gerencias' => $this->nombresGerencias($riesgoVista),
            'bloqueados' => $bloqueados,
            'propuestas' => $propuestas,
            'marcas' => $this->marcasDe($propuestas, 'controles'),
            'diffEnCurso' => $this->editando ? $this->construirDiff($riesgoVista) : [],
            'resultados' => $resultados,
        ]);
    }
}
