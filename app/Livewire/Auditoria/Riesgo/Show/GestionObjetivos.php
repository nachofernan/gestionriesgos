<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use Livewire\Component;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Estado;
use Illuminate\Support\Facades\Auth;

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
    public string $estadoModelo = 'borrador';
    public string $busqueda = '';
    public string $error = '';

    /** @var array<int, array{id:int, nombre:string}> */
    public array $seleccionados = [];

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
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
        if (!$objetivo) return;

        $this->seleccionados[] = [
            'id'             => $objetivo->id,
            'nombre'         => $objetivo->nombre,
            'descripcion'    => $objetivo->descripcion,
            'estado'         => $objetivo->estado?->nombre ?? 'borrador',
            'estado_color'   => $objetivo->estado?->color ?? 'gray',
            'area'           => $objetivo->area?->nombre,
            'fecha_objetivo' => $objetivo->fecha_objetivo?->format('d/m/Y'),
            'estrategico'    => (bool)$objetivo->estrategico,
            'anticorrupcion' => (bool)$objetivo->anticorrupcion,
            'puede_ver'      => Auth::user()->can('view', $objetivo),
            'url'            => route('auditoria.objetivos.show', $objetivo->id),
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
            array_filter($this->seleccionados, fn($o) => $o['id'] !== $objetivoId)
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

        $riesgo = Riesgo::findOrFail($this->riesgoId);
        $ids = collect($this->seleccionados)->pluck('id')->toArray();

        if ($this->esBorrador) {
            $riesgo->objetivos()->sync($ids);
            $this->editando = false;
            $this->error = '';
            session()->flash('ok', 'Objetivos actualizados.');
        } else {
            $estadoId = $this->estadoParaActualizacion();

            $riesgo->load('objetivos');
            $antesItems = $riesgo->objetivos->map(fn($o) => ['id' => $o->id, 'nombre' => $o->nombre]);
            $antesIds   = $antesItems->pluck('id');
            $despues    = collect($this->seleccionados);

            $diffRel = array_filter([
                'agrega' => $despues->filter(fn($o) => !$antesIds->contains($o['id']))->values()->toArray(),
                'quita'  => $antesItems->filter(fn($o) => !$despues->pluck('id')->contains($o['id']))->values()->toArray(),
            ], fn($a) => !empty($a));

            $data = ['tipo' => 'cambio', 'relaciones' => ['objetivos' => ['sync' => $ids]]];
            if (!empty($diffRel)) $data['diff'] = ['relaciones' => ['objetivos' => $diffRel]];

            $aplicarAhora = $estadoId === Estado::aprobado()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado');

            if ($aplicarAhora) {
                $riesgo->objetivos()->sync($ids);
                $riesgo->actualizaciones()->create([
                    'user_id'   => Auth::id(),
                    'mensaje'   => 'Objetivos asociados actualizados',
                    'estado_id' => $estadoId,
                    'data'      => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Objetivos actualizados.');
            } else {
                $riesgo->actualizaciones()->create([
                    'user_id'   => Auth::id(),
                    'mensaje'   => 'Propuesta de cambio en objetivos asociados',
                    'estado_id' => $estadoId,
                    'data'      => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Propuesta registrada. Pendiente de validación.');
            }
        }
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
        $this->esBorrador   = $this->estadoModelo === 'borrador';

        $user = Auth::user();
        $this->seleccionados = $riesgo->objetivos->map(fn($o) => [
            'id'             => $o->id,
            'nombre'         => $o->nombre,
            'descripcion'    => $o->descripcion,
            'estado'         => $o->estado?->nombre ?? 'borrador',
            'estado_color'   => $o->estado?->color ?? 'gray',
            'area'           => $o->area?->nombre,
            'fecha_objetivo' => $o->fecha_objetivo?->format('d/m/Y'),
            'estrategico'    => (bool)$o->estrategico,
            'anticorrupcion' => (bool)$o->anticorrupcion,
            'puede_ver'      => $user->can('view', $o),
            'url'            => route('auditoria.objetivos.show', $o->id),
        ])->values()->toArray();
    }

    public function render()
    {
        $yaIds = collect($this->seleccionados)->pluck('id');

        $resultados = $this->modalAbierto
            ? Objetivo::query()
                ->visiblePara(Auth::user())
                ->when($this->busqueda, fn($q) => $q->where('nombre', 'like', '%' . $this->busqueda . '%'))
                ->whereNotIn('id', $yaIds)
                ->orderBy('nombre')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.auditoria.riesgo.show.gestion-objetivos', [
            'resultados' => $resultados,
        ]);
    }
}
