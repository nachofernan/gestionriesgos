<?php

namespace App\Livewire\Auditoria\Riesgo\Index;

use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado/búsqueda de Riesgos con filtros por nombre, tipo, estado, área (con
 * opción de incluir sub-áreas) y "sólo alta criticidad", y orden por columna
 * (incluye ordenar por los accessors calculados valor_total/valor_residual).
 */
class Search extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $filtroTipo = null;

    public ?int $filtroEstado = null;

    public ?int $filtroArea = null;

    public bool $soloAlta = false;

    public bool $mostrarHijos = true;

    public string $ordenarPor = 'estado';

    public string $direccion = 'asc';

    protected $queryString = [
        'search' => ['except' => ''],
        'filtroTipo' => ['except' => null],
        'filtroEstado' => ['except' => null],
        'filtroArea' => ['except' => null],
        'soloAlta' => ['except' => false],
        'mostrarHijos' => ['except' => true],
        'ordenarPor' => ['except' => 'estado'],
        'direccion' => ['except' => 'asc'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroTipo(): void
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

    public function updatingSoloAlta(): void
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
        $this->filtroTipo = null;
        $this->filtroEstado = null;
        $this->filtroArea = null;
        $this->soloAlta = false;
        $this->mostrarHijos = true;
        $this->ordenarPor = 'estado';
        $this->direccion = 'asc';
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();

        // controles.estado, planesAccion.estado y planesAccion.tareas.estado: los
        // necesita el accessor valor_residual, que este listado muestra y usa para
        // ordenar. planesAccion excluye "borrado" (no aportan mitigación de todos
        // modos: PlanAccion::estaCompleto() sólo cuenta estado "aprobado", así que
        // filtrarlos acá no cambia el cálculo) para no mostrarlos en la columna
        // "Plan de acción".
        $query = Riesgo::query()
            ->with([
                'tipoRiesgo', 'estado', 'area', 'user',
                'controles.estado',
                'planesAccion' => fn ($q) => $q->whereNot('estado_id', Estado::borrado()->id),
                'planesAccion.estado', 'planesAccion.tareas.estado',
            ])
            ->visiblePara($user)
            ->leftJoin('estados', 'estados.id', '=', 'riesgos.estado_id')
            ->select('riesgos.*');

        // Búsqueda por nombre
        if ($this->search) {
            $query->where('riesgos.nombre', 'like', '%'.$this->search.'%');
        }

        // Filtro por tipo de riesgo
        if ($this->filtroTipo) {
            $query->where('tipo_riesgo_id', $this->filtroTipo);
        }

        // Filtro por estado
        if ($this->filtroEstado) {
            $query->where('riesgos.estado_id', $this->filtroEstado);
        }

        // Filtro por área
        if ($this->filtroArea) {
            $area = Area::find($this->filtroArea);
            if ($area) {
                $areaIds = $this->mostrarHijos ? $area->obtenerIdsSubarbol() : [$this->filtroArea];
                $query->whereIn('riesgos.area_id', $areaIds);
            }
        }

        if ($this->soloAlta) {
            $query->where('mayor_criticidad', true);
        }

        // valor_total/valor_residual son accessors calculados en PHP, no columnas:
        // no se pueden ordenar en SQL, así que se trae todo el resultado filtrado,
        // se ordena en memoria y se pagina a mano con Paginator.
        if (in_array($this->ordenarPor, ['valor_total', 'valor_residual'])) {
            $riesgos = $query->orderBy('nombre')->get();

            if ($this->ordenarPor === 'valor_total') {
                $riesgos = $riesgos->sortBy(fn ($r) => $r->valor_total, SORT_NUMERIC);
            } else {
                $riesgos = $riesgos->sortBy(fn ($r) => $r->valor_residual, SORT_NUMERIC);
            }

            if ($this->direccion === 'desc') {
                $riesgos = $riesgos->reverse();
            }

            // Paginar manualmente
            $page = request()->query('page', 1);
            $perPage = 15;
            $items = $riesgos->slice(($page - 1) * $perPage, $perPage)->values();
            $riesgos = new Paginator(
                $items,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        } else {
            $columna = $this->ordenarPor === 'estado'
                ? 'estados.nombre'
                : 'riesgos.'.$this->ordenarPor;
            $query->orderBy($columna, $this->direccion);
            $riesgos = $query->paginate(15);
        }

        return view('livewire.auditoria.riesgo.index.search', [
            'riesgos' => $riesgos,
            'tiposRiesgo' => TipoRiesgo::all(),
            'estados' => Estado::all(),
            'areas' => Area::all(),
        ]);
    }
}
