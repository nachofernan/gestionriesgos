<?php

namespace App\Livewire\Auditoria\Actualizaciones;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Modal de historial y gestión de Actualizaciones de una entidad genérica
 * (riesgo/control/objetivo/plan/tarea, indicada por modelType+modelId). Permite
 * proponer un cambio y validar/activar/cancelar/rechazar actualizaciones pendientes
 * inline, sin pasar por los controladores HTTP de cada entidad.
 */
class GestionActualizaciones extends Component
{
    public string $modelType;
    public int $modelId;
    public string $estadoModelo = '';

    public bool $modalAbierto = false;
    public string $mensaje = '';
    public array $cambios = [];

    protected $listeners = ['refrescarActualizaciones' => '$refresh'];

    public function mount(string $modelType, int $modelId): void
    {
        $this->modelType    = $modelType;
        $this->modelId      = $modelId;
        $this->estadoModelo = $this->resolverModelo()->estado?->nombre ?? '';
    }

    public function abrirModal(): void
    {
        $this->reset(['mensaje', 'cambios']);
        $this->cambios = array_fill_keys(array_keys($this->camposEditables()), '');
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
    }

    /**
     * Crea la Actualizacion con los cambios propuestos. El estado con el que nace
     * (borrador/validado/activo) depende del rol de quien la crea — ver
     * estadoParaActualizacion() — y si nace ya validada/activa sobre una entidad
     * que corresponde, los cambios se aplican al modelo en el mismo paso.
     */
    public function guardar(): void
    {
        $this->validate(['mensaje' => 'required|string|min:3']);

        $campos = collect($this->cambios)
            ->filter(fn($v) => $v !== null && $v !== '')
            ->toArray();

        $model    = $this->resolverModelo();
        $this->authorize('update', $model);
        $estadoId = $this->estadoParaActualizacion();

        $diff = [];
        foreach ($campos as $campo => $nuevo) {
            $antes = $model->$campo;
            if ($antes != $nuevo) {
                $diff[$campo] = ['antes' => $antes, 'despues' => $nuevo];
            }
        }

        $data = ['tipo' => 'cambio'];
        if (!empty($campos)) {
            $data['campos'] = $campos;
            if (!empty($diff)) $data['diff'] = ['campos' => $diff];
        }

        DB::transaction(function () use ($model, $campos, $estadoId, $data) {
            $aplicar = $estadoId === Estado::activo()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado');

            $dataFinal = empty($campos) ? ['tipo' => 'cambio'] : $data;
            if ($aplicar && !empty($campos)) {
                $dataFinal['activated_by'] = Auth::user()->name;
            }

            $model->actualizaciones()->create([
                'user_id'   => Auth::id(),
                'mensaje'   => $this->mensaje,
                'estado_id' => $estadoId,
                'data'      => $dataFinal,
            ]);

            if ($aplicar && !empty($campos)) {
                $model->update($campos);
            }
        });

        $this->cerrarModal();
    }

    /**
     * Regla de negocio: el comité editando una entidad ya activa genera la
     * actualización directamente en estado activo; gerente o comité en cualquier
     * otro caso saltean el borrador y van a validado; el resto arranca en borrador.
     */
    private function estadoParaActualizacion(): int
    {
        $user = Auth::user();

        if ($this->estadoModelo === 'activo' && $user->esComite()) {
            return Estado::activo()->id;
        }

        if ($user->esGerente() || $user->esComite()) {
            return Estado::validado()->id;
        }

        return Estado::borrador()->id;
    }

    /**
     * Valida la actualización y, si la entidad ya estaba en estado "validado",
     * la activa en el mismo paso aplicando sus cambios (ver estadoModelo).
     */
    public function validarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('validar', $actualizacion);

        DB::transaction(function () use ($actualizacion) {
            $actualizacion->update(['estado_id' => Estado::validado()->id]);

            if ($this->estadoModelo === 'validado') {
                $actualizacion->update([
                    'data' => array_merge($actualizacion->data ?? [], ['activated_by' => Auth::user()->name]),
                ]);
                $this->aplicarCambiosDesdeActualizacion($actualizacion);
            }
        });
    }

    public function cancelarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('cancelar', $actualizacion);

        $actualizacion->update(['estado_id' => Estado::borrado()->id]);
    }

    /**
     * Activa la actualización y aplica sus cambios. Soporta tanto el formato
     * nuevo (`data['campos']`/`data['relaciones']`) como el legacy, donde `data`
     * son directamente los campos a actualizar (se excluyen las claves de control
     * tipo/diff/activated_by antes de pasarlos a update()).
     */
    public function activarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('activar', $actualizacion);

        DB::transaction(function () use ($actualizacion) {
            $actualizacion->update([
                'estado_id' => Estado::activo()->id,
                'data'      => array_merge($actualizacion->data ?? [], ['activated_by' => Auth::user()->name]),
            ]);

            $data = $actualizacion->fresh()->data ?? [];
            if (empty($data)) return;

            $model = $actualizacion->actualizable;

            if (isset($data['campos']) || isset($data['relaciones'])) {
                if (!empty($data['campos'])) {
                    $model->update($data['campos']);
                }
            } else {
                $model->update(collect($data)->except(['tipo', 'diff', 'activated_by'])->toArray());
            }

            if (!empty($data['relaciones'])) {
                foreach ($data['relaciones'] as $relacion => $ops) {
                    if (isset($ops['sync'])) $model->$relacion()->sync($ops['sync']);
                    if (isset($ops['attach'])) $model->$relacion()->attach($ops['attach']);
                    if (isset($ops['detach'])) $model->$relacion()->detach($ops['detach']);
                }
            }
        });
    }

    public function rechazarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('rechazar', $actualizacion);

        $actualizacion->update(['estado_id' => Estado::borrado()->id]);
    }

    /**
     * Aplica sobre la entidad relacionada los cambios guardados en `data`
     * (campos + sync/attach/detach de relaciones). Misma lógica que
     * ActualizacionController::aplicarCambios(), duplicada para este componente.
     */
    private function aplicarCambiosDesdeActualizacion(Actualizacion $actualizacion): void
    {
        $data = $actualizacion->data ?? [];
        if (empty($data)) return;

        $tipo = $data['tipo'] ?? null;
        if ($tipo !== null && $tipo !== 'cambio') return;

        $model = $actualizacion->actualizable;

        if (!empty($data['campos'])) {
            $model->update($data['campos']);
        }

        if (!empty($data['relaciones'])) {
            foreach ($data['relaciones'] as $relacion => $ops) {
                if (isset($ops['sync']))   $model->$relacion()->sync($ops['sync']);
                if (isset($ops['attach'])) $model->$relacion()->attach($ops['attach']);
                if (isset($ops['detach'])) $model->$relacion()->detach($ops['detach']);
            }
        }
    }

    private function resolverModelo(): Model
    {
        return match($this->modelType) {
            'riesgo'  => Riesgo::findOrFail($this->modelId),
            'control' => Control::findOrFail($this->modelId),
            'objetivo' => Objetivo::findOrFail($this->modelId),
            'plan'    => PlanAccion::findOrFail($this->modelId),
            'tarea'   => Tarea::findOrFail($this->modelId),
        };
    }

    /**
     * Whitelist de campos editables por tipo de entidad: define tanto los inputs
     * que se renderizan en el modal como los únicos campos que guardar() puede
     * llegar a escribir.
     */
    private function camposEditables(): array
    {
        return match($this->modelType) {
            'riesgo'  => [
                'nombre'       => 'Nombre',
                'descripcion'  => 'Descripción',
                'impacto'      => 'Impacto',
                'probabilidad' => 'Probabilidad',
            ],
            'control' => [
                'nombre'             => 'Nombre',
                'descripcion'        => 'Descripción',
                'mitigacion_default' => 'Mitigación por defecto',
            ],
            'objetivo' => [
                'nombre'         => 'Nombre',
                'descripcion'    => 'Descripción',
                'fecha_objetivo' => 'Fecha objetivo',
            ],
            'plan' => [
                'nombre'      => 'Nombre',
                'descripcion' => 'Descripción',
            ],
            'tarea' => [
                'nombre'            => 'Nombre',
                'descripcion'       => 'Descripción',
                'porcentaje_avance' => 'Porcentaje de avance',
                'fecha'             => 'Fecha',
            ],
            default => [],
        };
    }

    public function render()
    {
        $actualizaciones = $this->resolverModelo()
            ->actualizaciones()
            ->with(['user', 'estado'])
            ->latest('created_at')
            ->get();

        return view('livewire.auditoria.actualizaciones.gestion-actualizaciones', [
            'actualizaciones'  => $actualizaciones,
            'camposEditables'  => $this->camposEditables(),
        ]);
    }
}
