<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use Livewire\Component;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use Illuminate\Support\Facades\Auth;

/**
 * Gestión de las gerencias (Área) asociadas a un Riesgo (patrón $seleccionados
 * en memoria → guardar()). Todas las gerencias asociadas tienen los mismos
 * permisos de gestión (ver Riesgo::puedeGestionarAlgunaArea() y RiesgoPolicy).
 * Un riesgo siempre debe conservar al menos una gerencia asociada — quitar() y
 * guardar() lo validan. Si el riesgo está en borrador, sincroniza directo; si
 * no, la asociación queda como una Actualizacion (propuesta de cambio) que se
 * aplica de inmediato sólo si el estado resultante lo amerita (ver
 * estadoParaActualizacion()), igual que GestionObjetivos/GestionControles.
 */
class GestionAreas extends Component
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

    public function agregar(int $areaId): void
    {
        if (collect($this->seleccionados)->contains('id', $areaId)) {
            return;
        }

        $area = Area::find($areaId);
        if (!$area) return;

        $this->seleccionados[] = [
            'id'     => $area->id,
            'nombre' => $area->nombre,
        ];

        $this->error = '';
        $this->cerrarModal();
    }

    public function quitar(int $areaId): void
    {
        if (count($this->seleccionados) <= 1) {
            $this->error = 'El riesgo debe tener al menos una gerencia asociada.';
            return;
        }

        $this->seleccionados = array_values(
            array_filter($this->seleccionados, fn($a) => $a['id'] !== $areaId)
        );
        $this->error = '';
    }

    /**
     * Si el riesgo está en borrador, sincroniza gerencias de inmediato. Si no,
     * arma el diff (agrega/quita) y lo guarda como Actualizacion; según el
     * estado que le toque (estadoParaActualizacion()) la aplica en el momento o
     * la deja pendiente de validación.
     */
    public function guardar(): void
    {
        if (empty($this->seleccionados)) {
            $this->error = 'Debe seleccionar al menos una gerencia.';
            return;
        }

        $riesgo = Riesgo::findOrFail($this->riesgoId);
        $ids = collect($this->seleccionados)->pluck('id')->toArray();

        if ($this->esBorrador) {
            $riesgo->areas()->sync($ids);
            $this->editando = false;
            $this->error = '';
            session()->flash('ok', 'Gerencias actualizadas.');
        } else {
            $estadoId = $this->estadoParaActualizacion();

            $riesgo->load('areas');
            $antesItems = $riesgo->areas->map(fn($a) => ['id' => $a->id, 'nombre' => $a->nombre]);
            $antesIds   = $antesItems->pluck('id');
            $despues    = collect($this->seleccionados);

            $diffRel = array_filter([
                'agrega' => $despues->filter(fn($a) => !$antesIds->contains($a['id']))->values()->toArray(),
                'quita'  => $antesItems->filter(fn($a) => !$despues->pluck('id')->contains($a['id']))->values()->toArray(),
            ], fn($a) => !empty($a));

            $data = ['tipo' => 'cambio', 'relaciones' => ['areas' => ['sync' => $ids]]];
            if (!empty($diffRel)) $data['diff'] = ['relaciones' => ['areas' => $diffRel]];

            $aplicarAhora = $estadoId === Estado::aprobado()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado');

            if ($aplicarAhora) {
                $riesgo->areas()->sync($ids);
                $riesgo->actualizaciones()->create([
                    'user_id'   => Auth::id(),
                    'mensaje'   => 'Gerencias asociadas actualizadas',
                    'estado_id' => $estadoId,
                    'data'      => $data,
                ]);
                $this->cancelarEdicion();
                session()->flash('ok', 'Gerencias actualizadas.');
            } else {
                $riesgo->actualizaciones()->create([
                    'user_id'   => Auth::id(),
                    'mensaje'   => 'Propuesta de cambio en gerencias asociadas',
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
        $riesgo = Riesgo::with(['areas', 'estado'])->findOrFail($this->riesgoId);
        $this->estadoModelo = $riesgo->estado?->nombre ?? 'borrador';
        $this->esBorrador   = $this->estadoModelo === 'borrador';

        $this->seleccionados = $riesgo->areas->map(fn($a) => [
            'id'     => $a->id,
            'nombre' => $a->nombre,
        ])->values()->toArray();
    }

    public function render()
    {
        $yaIds = collect($this->seleccionados)->pluck('id');

        $resultados = $this->modalAbierto
            ? Area::query()
                ->when($this->busqueda, fn($q) => $q->where('nombre', 'like', '%' . $this->busqueda . '%'))
                ->whereNotIn('id', $yaIds)
                ->orderBy('nombre')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.auditoria.riesgo.show.gestion-areas', [
            'resultados' => $resultados,
        ]);
    }
}
