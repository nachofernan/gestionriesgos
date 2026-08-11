<?php

namespace App\Livewire\Auditoria\PlanAccion\Index;

use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado/búsqueda de Planes de Acción con filtros por nombre/código, estado y
 * área (con opción de incluir sub-áreas) y orden por columna.
 */
class Search extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $filtroEstado = null;

    public ?int $filtroArea = null;

    public bool $mostrarHijos = true;

    public string $ordenarPor = 'nombre';

    public string $direccion = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'filtroEstado' => ['except' => null],
        'filtroArea' => ['except' => null],
        'mostrarHijos' => ['except' => true],
        'ordenarPor' => ['except' => 'nombre'],
        'direccion' => ['except' => 'asc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroArea(): void
    {
        $this->resetPage();
    }

    public function updatingMostrarHijos(): void
    {
        $this->resetPage();
    }

    public function ordenar(string $columna): void
    {
        if ($this->ordenarPor === $columna) {
            $this->direccion = $this->direccion === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $columna;
            $this->direccion = 'asc';
        }
    }

    public function limpiarFiltros(): void
    {
        $this->search = '';
        $this->filtroEstado = null;
        $this->filtroArea = null;
        $this->mostrarHijos = true;
        $this->ordenarPor = 'nombre';
        $this->direccion = 'asc';
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();

        // riesgos filtrados por visibilidad: un borrador de otra gerencia no debe
        // aparecer en la columna "Riesgos" del listado (axioma 1 + scopeVisiblePara).
        // Los "borrado" (rechazados) tampoco: quedan en limbo, no se muestran.
        $query = PlanAccion::query()
            ->with([
                'area', 'user', 'tareas.estado', 'estado',
                'riesgos' => fn ($q) => $q->visiblePara($user)->whereNot('estado_id', Estado::borrado()->id),
            ])
            ->visiblePara($user);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%'.$this->search.'%')
                    ->orWhere('codigo', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->filtroEstado) {
            $query->where('estado_id', $this->filtroEstado);
        }

        if ($this->filtroArea) {
            $area = Area::find($this->filtroArea);
            if ($area) {
                $areaIds = $this->mostrarHijos ? $area->obtenerIdsSubarbol() : [$this->filtroArea];
                $query->whereIn('area_id', $areaIds);
            }
        }

        $query->orderBy($this->ordenarPor, $this->direccion);

        return view('livewire.auditoria.plan-accion.index.search', [
            'planes' => $query->paginate(15),
            'estados' => Estado::all(),
            'areas' => Area::all(),
        ]);
    }
}
