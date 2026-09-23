<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Gestión de las gerencias (Área tipo Gerencia) asociadas a un Riesgo (patrón
 * $seleccionados en memoria → guardar()). Esta UI opera SÓLO sobre las entradas
 * de tipo gerencia del pivot area_riesgo: el área puntual del creador también
 * vive en el pivot (la sincroniza Riesgo::booted() al crear) pero es un permiso
 * implícito que no se muestra ni se gestiona acá; cargar()/guardar() la ignoran
 * al listar y la preservan al sincronizar para no romper el acceso del creador a
 * su propio borrador. El buscador de agregar sólo ofrece áreas ya marcadas como
 * gerencia. Todas las gerencias asociadas tienen los mismos permisos de gestión
 * (ver Riesgo::puedeGestionarAlgunaArea() y RiesgoPolicy). Un riesgo siempre debe
 * conservar al menos una gerencia asociada — quitar() y guardar() lo validan. Si
 * el riesgo está en borrador, sincroniza directo; si no, la asociación queda como
 * una Actualizacion (propuesta de cambio) que se aplica de inmediato sólo si el
 * estado resultante lo amerita (ver estadoParaActualizacion()), igual que
 * GestionObjetivos/GestionControles. Dispatcha 'riesgo-actualizado' al persistir
 * un cambio real, para que InfoRiesgo y GestionActualizaciones se refresquen solos.
 */
class GestionAreas extends Component
{
    use AuthorizesRequests;

    public int $riesgoId;

    public bool $modalAbierto = false;

    public bool $editando = false;

    public string $estadoModelo = 'borrador';

    /** Si el usuario puede agregar/quitar gerencias (gerente + riesgo ya validado). */
    public bool $puedeGestionar = false;

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
        $this->authorize('gestionarGerencias', Riesgo::findOrFail($this->riesgoId));
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
        if (! $area) {
            return;
        }

        $this->seleccionados[] = [
            'id' => $area->id,
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
            array_filter($this->seleccionados, fn ($a) => $a['id'] !== $areaId)
        );
        $this->error = '';
    }

    /**
     * Registra el cambio de gerencias como una Actualizacion (propuesta), con el
     * diff legible de lo agregado/quitado. Según el estado que le toque
     * (estadoParaActualizacion()) se aplica en el momento o queda pendiente de
     * validación. Sólo se llega acá con el riesgo ya validado/aprobado y por un
     * gerente (ver policy gestionarGerencias); un borrador nunca se comparte.
     */
    public function guardar(): void
    {
        if (empty($this->seleccionados)) {
            $this->error = 'Debe seleccionar al menos una gerencia.';

            return;
        }

        $riesgo = Riesgo::with(['areas', 'estado'])->findOrFail($this->riesgoId);
        $this->authorize('gestionarGerencias', $riesgo);

        // No se apilan cambios de gerencias sobre una propuesta todavía sin resolver:
        // primero hay que validar (o rechazar) lo pendiente. Así el padrón de
        // gerencias que debe votar una propuesta queda fijo mientras se resuelve.
        if ($riesgo->actualizaciones()->where('estado_id', Estado::borrador()->id)->exists()) {
            $this->error = 'Hay una propuesta pendiente de validación en este riesgo. Resolvela antes de cambiar las gerencias.';

            return;
        }

        // Las entradas no-gerencia del pivot (el área puntual del creador) no se
        // gestionan desde esta UI: se preservan al sincronizar para no dejar sin
        // acceso a quien creó el borrador. $seleccionados sólo trae gerencias.
        $idsOcultos = $riesgo->areas->reject->esGerencia()->pluck('id');
        $ids = $idsOcultos
            ->merge(collect($this->seleccionados)->pluck('id'))
            ->unique()
            ->values()
            ->toArray();

        // Si el riesgo ya es compartido (>=2 gerencias), este cambio no se aplica de
        // una: nace pendiente y necesita el voto de todas. Pasar de 1 a 2 gerencias
        // no cuenta (el padrón previo es una sola), así que la primera vez se aplica
        // con la sola validación del proponente. El comité queda afuera de la regla.
        $dobleValidacion = $riesgo->cambioRequiereDobleValidacion(Auth::user());

        $estadoId = $dobleValidacion ? Estado::borrador()->id : $this->estadoParaActualizacion();

        // El diff (registro de auditoría legible) se calcula sólo sobre las
        // gerencias visibles: el área puntual oculta no es un cambio que el
        // usuario haya hecho ni se ve en esta UI.
        $antesItems = $riesgo->areas->filter->esGerencia()
            ->map(fn ($a) => ['id' => $a->id, 'nombre' => $a->nombre])->values();
        $antesIds = $antesItems->pluck('id');
        $despues = collect($this->seleccionados);

        $diffRel = array_filter([
            'agrega' => $despues->filter(fn ($a) => ! $antesIds->contains($a['id']))->values()->toArray(),
            'quita' => $antesItems->filter(fn ($a) => ! $despues->pluck('id')->contains($a['id']))->values()->toArray(),
        ], fn ($a) => ! empty($a));

        $data = ['tipo' => 'cambio', 'relaciones' => ['areas' => ['sync' => $ids]]];
        if (! empty($diffRel)) {
            $data['diff'] = ['relaciones' => ['areas' => $diffRel]];
        }

        $aplicarAhora = ! $dobleValidacion && ($estadoId === Estado::aprobado()->id
            || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado'));

        if ($aplicarAhora) {
            // El cambio se aplica en el acto: se marca activated_by para que el
            // historial lo rotule "Cambios aplicados" y no "Cambios propuestos".
            $data['activated_by'] = Auth::user()->name;
            $riesgo->areas()->sync($ids);
            $riesgo->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Gerencias asociadas actualizadas',
                'estado_id' => $estadoId,
                'data' => $data,
            ]);
            $this->dispatch('riesgo-actualizado');
            $this->cancelarEdicion();
            session()->flash('ok', 'Gerencias actualizadas.');
        } else {
            $actualizacion = $riesgo->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Propuesta de cambio en gerencias asociadas',
                'estado_id' => $estadoId,
                'data' => $data,
            ]);

            // El proponente vota a favor por su propia gerencia al crear la propuesta.
            if ($dobleValidacion) {
                $actualizacion->registrarVoto(Auth::user(), true);
            }

            $this->dispatch('riesgo-actualizado');
            $this->cancelarEdicion();
            session()->flash('ok', 'Propuesta registrada. Pendiente de validación.');
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
        $this->puedeGestionar = Auth::user()->can('gestionarGerencias', $riesgo);

        $this->seleccionados = $riesgo->areas
            ->filter->esGerencia()
            ->map(fn ($a) => [
                'id' => $a->id,
                'nombre' => $a->nombre,
            ])->values()->toArray();
    }

    public function render()
    {
        $yaIds = collect($this->seleccionados)->pluck('id');

        $resultados = $this->modalAbierto
            ? Area::query()
                ->where('tipo', TipoArea::Gerencia)
                ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', '%'.$this->busqueda.'%'))
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
