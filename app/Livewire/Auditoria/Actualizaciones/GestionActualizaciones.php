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
use Livewire\WithFileUploads;

/**
 * Modal de historial y gestión de Actualizaciones de una entidad genérica
 * (riesgo/control/objetivo/plan/tarea, indicada por modelType+modelId). Permite
 * proponer un cambio y validar/aprobar/cancelar/rechazar actualizaciones pendientes
 * inline, sin pasar por los controladores HTTP de cada entidad.
 */
class GestionActualizaciones extends Component
{
    use WithFileUploads;

    public string $modelType;

    public int $modelId;

    public string $estadoModelo = '';

    public bool $modalAbierto = false;

    public string $mensaje = '';

    public array $cambios = [];

    /** Adjuntos temporales de Livewire para la actualización que se está creando. */
    public array $archivos = [];

    protected $listeners = ['refrescarActualizaciones' => '$refresh'];

    public function mount(string $modelType, int $modelId): void
    {
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->estadoModelo = $this->resolverModelo()->estado?->nombre ?? '';
    }

    public function abrirModal(): void
    {
        $this->reset(['mensaje', 'cambios', 'archivos']);
        $this->cambios = array_fill_keys(array_keys($this->camposEditables()), '');
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
    }

    /**
     * Crea la Actualizacion con los cambios propuestos. El estado con el que nace
     * (borrador/validado/aprobado) depende del rol de quien la crea — ver
     * estadoParaActualizacion() — y si nace ya validada/aprobada sobre una entidad
     * que corresponde, los cambios se aplican al modelo en el mismo paso.
     */
    public function guardar(): void
    {
        $this->validate([
            'mensaje' => 'required|string|min:3',
            'archivos.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ]);

        $campos = collect($this->cambios)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->toArray();

        $model = $this->resolverModelo();
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
        if (! empty($campos)) {
            $data['campos'] = $campos;
            if (! empty($diff)) {
                $data['diff'] = ['campos' => $diff];
            }
        }

        $actualizacion = DB::transaction(function () use ($model, $campos, $estadoId, $data) {
            $aplicar = $estadoId === Estado::aprobado()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado');

            $dataFinal = empty($campos) ? ['tipo' => 'cambio'] : $data;
            if ($aplicar && ! empty($campos)) {
                $dataFinal['activated_by'] = Auth::user()->name;
            }

            $actualizacion = $model->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => $this->mensaje,
                'estado_id' => $estadoId,
                'data' => $dataFinal,
            ]);

            if ($aplicar && ! empty($campos)) {
                $model->update($campos);
            }

            return $actualizacion;
        });

        // El attach de medios no es transaccional (mueve archivos en disco), así que
        // va después del commit, sobre la actualización ya persistida.
        foreach ($this->archivos as $archivo) {
            $actualizacion->addMedia($archivo->getRealPath())
                ->usingFileName($archivo->getClientOriginalName())
                ->toMediaCollection('adjuntos');
        }

        $this->cerrarModal();
    }

    /**
     * Regla de negocio: el comité editando una entidad ya aprobada genera la
     * actualización directamente en estado aprobado; gerente o comité en cualquier
     * otro caso saltean el borrador y van a validado; el resto arranca en borrador.
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

    /**
     * Valida la actualización y, si la entidad ya estaba en estado "validado",
     * la aprueba en el mismo paso aplicando sus cambios (ver Actualizacion::marcarValidada()).
     */
    public function validarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('validar', $actualizacion);
        $actualizacion->marcarValidada(Auth::user());
    }

    public function cancelarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('cancelar', $actualizacion);

        $actualizacion->update(['estado_id' => Estado::borrado()->id]);
    }

    public function aprobarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('aprobar', $actualizacion);
        $actualizacion->marcarAprobada(Auth::user());
    }

    public function rechazarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('rechazar', $actualizacion);
        $actualizacion->marcarRechazada();
    }

    private function resolverModelo(): Model
    {
        return match ($this->modelType) {
            'riesgo' => Riesgo::findOrFail($this->modelId),
            'control' => Control::findOrFail($this->modelId),
            'objetivo' => Objetivo::findOrFail($this->modelId),
            'plan' => PlanAccion::findOrFail($this->modelId),
            'tarea' => Tarea::findOrFail($this->modelId),
        };
    }

    /**
     * Whitelist de campos editables por tipo de entidad: define tanto los inputs
     * que se renderizan en el modal como los únicos campos que guardar() puede
     * llegar a escribir.
     */
    private function camposEditables(): array
    {
        return match ($this->modelType) {
            'riesgo' => [
                'nombre' => 'Nombre',
                'descripcion' => 'Descripción',
                'impacto' => 'Impacto',
                'probabilidad' => 'Probabilidad',
            ],
            'control' => [
                'nombre' => 'Nombre',
                'descripcion' => 'Descripción',
                'mitigacion_default' => 'Mitigación por defecto',
            ],
            'objetivo' => [
                'nombre' => 'Nombre',
                'descripcion' => 'Descripción',
                'fecha_objetivo' => 'Fecha objetivo',
            ],
            'plan' => [
                'nombre' => 'Nombre',
                'descripcion' => 'Descripción',
            ],
            'tarea' => [
                'nombre' => 'Nombre',
                'descripcion' => 'Descripción',
                'porcentaje_avance' => 'Porcentaje de avance',
                'fecha' => 'Fecha',
            ],
            default => [],
        };
    }

    public function render()
    {
        $actualizaciones = $this->resolverModelo()
            ->actualizaciones()
            ->with(['user', 'estado', 'media'])
            ->latest('created_at')
            ->get();

        return view('livewire.auditoria.actualizaciones.gestion-actualizaciones', [
            'actualizaciones' => $actualizaciones,
            'camposEditables' => $this->camposEditables(),
        ]);
    }
}
