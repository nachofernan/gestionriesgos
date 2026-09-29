<?php

namespace App\Livewire\Auditoria\Riesgo\Show\Concerns;

use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Búsqueda y filtros del modal "Agregar control/plan/objetivo" (x-auditoria.modal-buscar).
 * Los tres componentes de gestión (GestionControles, GestionPlanes, GestionObjetivos)
 * lo comparten para que se comporten igual. Los filtros sólo RESTRINGEN una consulta
 * que ya pasó por visiblePara(): nunca amplían lo que el usuario puede ver (axioma 1).
 * Test que lo cubre: ModalBuscarFiltrosTest.
 */
trait BuscaEnModal
{
    public string $busqueda = '';

    /** Área (con su subárbol) de los resultados; null = "Todas". Arranca en el área del usuario. */
    public ?int $filtroArea = null;

    public ?int $filtroEstado = null;

    /** Deja el modal en su estado de apertura: sin texto, mostrando lo propio (el comité, todo). */
    protected function reiniciarFiltrosModal(): void
    {
        $this->busqueda = '';
        $this->filtroArea = Auth::user()->area_id;
        $this->filtroEstado = null;
    }

    /** Acota la consulta ya visible por el área (incluye sub-áreas) y el estado elegidos. */
    protected function aplicarFiltrosModal(Builder $query, string $tabla): Builder
    {
        return $query
            ->when($this->filtroArea, function ($q) use ($tabla) {
                $area = Area::find($this->filtroArea);
                $q->whereIn($tabla.'.area_id', $area ? $area->obtenerIdsSubarbol() : [$this->filtroArea]);
            })
            ->when($this->filtroEstado, fn ($q) => $q->where($tabla.'.estado_id', $this->filtroEstado));
    }

    /** Opciones de los selects del modal. */
    protected function opcionesFiltrosModal(): array
    {
        return [
            'areasModal' => Area::orderBy('nombre')->get(['id', 'nombre']),
            'estadosModal' => Estado::todosOrdenados()->reject(fn ($e) => $e->nombre === 'borrado'),
        ];
    }
}
