<?php

namespace App\Livewire\Auditoria\Actualizaciones;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\TipoRiesgo;
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

    /** Gobierna la visibilidad del botón "Nueva Actualización" en la vista. */
    public bool $puedeActualizar = false;

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
        $modelo = $this->resolverModelo();
        $this->estadoModelo = $modelo->estado?->nombre ?? '';
        $this->puedeActualizar = Auth::user()->can('update', $modelo);
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
        // Los campos de fecha se validan en el borde: un texto libre entra por la
        // propiedad pública `cambios` y la entidad castea fecha a date, así que sin
        // esto se podría guardar basura. Sólo se valida el campo si trae valor.
        $reglas = [
            'mensaje' => 'required|string|min:3',
            'archivos.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ];
        foreach ($this->camposFecha() as $campo) {
            if (($this->cambios[$campo] ?? '') !== '') {
                $reglas["cambios.$campo"] = 'date';
            }
        }
        foreach ($this->camposNumericos() as $campo => $rango) {
            if (($this->cambios[$campo] ?? '') !== '') {
                $reglas["cambios.$campo"] = "integer|min:{$rango['min']}|max:{$rango['max']}";
            }
        }

        $this->validate($reglas, [
            'cambios.*.date' => 'Ingresá una fecha válida.',
            'cambios.*.integer' => 'Ingresá un número entero.',
            'cambios.*.min' => 'El valor mínimo es :min.',
            'cambios.*.max' => 'El valor máximo es :max.',
        ]);

        $campos = collect($this->cambios)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->toArray();

        $model = $this->resolverModelo();
        $this->authorize('update', $model);

        // Un riesgo con dos o más gerencias no aplica un cambio de una: la propuesta
        // nace pendiente (borrador) y necesita el voto de todas las gerencias (ver
        // Riesgo::cambioRequiereDobleValidacion()). El comité queda afuera: es la
        // cúspide y valida solo, sin depender de las gerencias.
        $dobleValidacion = $model instanceof Riesgo
            && $model->cambioRequiereDobleValidacion(Auth::user());

        $estadoId = $dobleValidacion ? Estado::borrador()->id : $this->estadoParaActualizacion();

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

        $actualizacion = DB::transaction(function () use ($model, $campos, $estadoId, $data, $dobleValidacion) {
            $aplicar = ! $dobleValidacion && ($estadoId === Estado::aprobado()->id
                || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado'));

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

            // El proponente vota a favor por su propia gerencia al crear la propuesta.
            if ($dobleValidacion) {
                $actualizacion->registrarVoto(Auth::user(), true);
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
     *
     * Bajo doble validación (riesgo con varias gerencias) esto no valida directo:
     * registra el voto a favor de la gerencia del usuario y sólo cuando TODAS las
     * gerencias votaron a favor se marca validada (y se aplica).
     */
    public function validarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('validar', $actualizacion);

        if ($actualizacion->requiereDobleValidacion()) {
            $actualizacion->registrarVoto(Auth::user(), true);
            if ($actualizacion->todasLasGerenciasValidaron()) {
                $actualizacion->marcarValidada(Auth::user());
            }

            return;
        }

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

    /**
     * Rechaza la propuesta. Bajo doble validación deja registrado el voto en contra
     * de la gerencia; un solo rechazo tumba el cambio y queda todo como estaba.
     */
    public function rechazarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('rechazar', $actualizacion);

        if ($actualizacion->requiereDobleValidacion()) {
            $actualizacion->registrarVoto(Auth::user(), false);
        }

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
     * Campos editables por tipo de entidad: gobierna qué inputs se renderizan en
     * el modal. No es una whitelist server-side: guardar() escribe lo que venga en
     * la propiedad pública `cambios`, así que este listado acota la UI, no lo que
     * el componente podría llegar a escribir. La doble validación de cambios de
     * campos de un riesgo compartido sigue viva por debajo aunque el modal ya no
     * exponga esos campos ('riesgo' => []); se re-expondrá cuando se resuelva el
     * mecanismo pendiente para nombre/descripción.
     */
    private function camposEditables(): array
    {
        return match ($this->modelType) {
            // Un riesgo se actualiza solo con mensaje + adjunto: impacto y probabilidad
            // son calculados por el wizard de creación/recálculo (no se tipean a mano),
            // y nombre/descripción quedan pendientes de resolverse por otro mecanismo.
            'riesgo' => [],
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

    /**
     * Campos de `camposEditables()` que representan fechas: el modal los renderiza
     * como <input type="date"> y guardar() los valida como fecha. La lista es genérica
     * (fecha en tarea, fecha_objetivo en objetivo); para el resto de entidades el
     * in_array simplemente no matchea y quedan como texto.
     */
    private function camposFecha(): array
    {
        return ['fecha', 'fecha_objetivo'];
    }

    /**
     * Campos de `camposEditables()` con rango numérico: el modal los renderiza como
     * <input type="number" min max> y guardar() los valida contra el mismo rango.
     * mitigacion_default de Control replica el tope de 1-10 que ya rige en su
     * creación/edición (ControlController) y en el valor por riesgo (GestionControles).
     * porcentaje_avance de Tarea replica el tope de 0-100 que ya rige en
     * TareaController, ActualizacionTareaController y GestionTareas::guardarNuevaTarea().
     */
    private function camposNumericos(): array
    {
        return [
            'mitigacion_default' => ['min' => 1, 'max' => 10],
            'porcentaje_avance' => ['min' => 0, 'max' => 100],
        ];
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
            'camposFecha' => $this->camposFecha(),
            'camposNumericos' => $this->camposNumericos(),
            // Resuelve tipo_riesgo_id → nombre en la entrada de creación de un riesgo
            // (una consulta liviana; para el resto de entidades queda vacío e inocuo).
            'tiposRiesgo' => TipoRiesgo::pluck('nombre', 'id'),
        ]);
    }
}
