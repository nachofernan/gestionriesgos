<?php

namespace App\Livewire\Auditoria\Tarea\Index;

use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Tarea;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado/búsqueda de Tareas con filtros por nombre, estado y área (con
 * opción de incluir sub-áreas) y orden por columna.
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

        // planesAccion filtrados por visibilidad: un borrador de otra gerencia no
        // debe aparecer en la columna "Planes" del listado (axioma 1 +
        // scopeVisiblePara). Los "borrado" tampoco, quedan en limbo.
        $query = Tarea::query()
            ->with([
                'area', 'user', 'estado',
                'planesAccion' => fn ($q) => $q->visiblePara($user)->whereNot('estado_id', Estado::borrado()->id),
            ])
            ->visiblePara($user);

        if ($this->search) {
            $query->where('tareas.nombre', 'like', '%'.$this->search.'%');
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

        // El estado manda siempre como criterio primario (aprobado, validado,
        // borrador, borrado); la columna elegida por el usuario es secundaria.
        $query->join('estados', 'estados.id', '=', 'tareas.estado_id')
            ->select('tareas.*')
            ->orderByRaw(Estado::ordenSql().' asc')
            ->orderBy('tareas.'.$this->ordenarPor, $this->direccion);

        return view('livewire.auditoria.tarea.index.search', [
            'tareas' => $query->paginate(15),
            'estados' => Estado::todosOrdenados(),
            'areas' => Area::all(),
        ]);
    }
}
