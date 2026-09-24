<?php

namespace App\Livewire\Auditoria\Actualizaciones;

use App\Enums\Auditoria\RespuestaRiesgo;
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
use Livewire\Attributes\On;
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

    /**
     * Cómo se pinta el historial: 'completa' (lista con el detalle de cada
     * entrada y el modal de nueva actualización, la de siempre) o 'timeline'
     * (línea de tiempo de sólo lectura para la columna lateral de riesgo/show: las
     * propuestas se resuelven en su bloque y las notas viven en la Conversación).
     */
    public string $variante = 'completa';

    /** Variante 'timeline': modal con el detalle completo de la actividad. */
    public bool $actividadAbierta = false;

    /** Variante 'timeline': filtro del modal (todo/cambios/pendientes/rechazadas/notas/adjuntos). */
    public string $filtroActividad = 'todo';

    /** Variante 'timeline': parte tocada por la que se filtra el modal (campos/areas/objetivos/…) o '' para todas. */
    public string $parteActividad = '';

    /** Variante 'timeline': entrada resaltada al abrir el modal desde el lateral. */
    public ?int $focoActividad = null;

    public string $mensaje = '';

    public array $cambios = [];

    /** Adjuntos temporales de Livewire para la actualización que se está creando. */
    public array $archivos = [];

    /**
     * Otros bloques hermanos de la misma pantalla (GestionAreas/Objetivos/
     * Controles/Planes/Tareas, y este mismo componente en sus propias acciones
     * de validar/aprobar, ver dispatch() más abajo) avisan con
     * "{tipo}-actualizado" cuando persisten un cambio real sobre la entidad; acá
     * no hace falta más que escucharlo para que el historial se re-renderice
     * fresco (ver render(), que ya consulta la DB de cero en cada llamada). El
     * placeholder `{modelType}` lo resuelve Livewire contra la propiedad pública
     * del mismo nombre, así cada instancia escucha sólo el evento de su propia
     * entidad (riesgo-actualizado / control-actualizado / etc.).
     */
    #[On('{modelType}-actualizado')]
    public function refrescar(): void {}

    public function mount(string $modelType, int $modelId, string $variante = 'completa'): void
    {
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->variante = $variante;
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

    public function abrirActividad(?int $id = null): void
    {
        $this->reset(['filtroActividad', 'parteActividad']);
        $this->focoActividad = $id;
        $this->actividadAbierta = true;
        if ($id) {
            $this->js("setTimeout(() => document.getElementById('act-det-{$id}')?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 50)");
        }
    }

    public function cerrarActividad(): void
    {
        $this->actividadAbierta = false;
        $this->focoActividad = null;
    }

    /**
     * Resuelve una propuesta desde la tarjeta que la muestra dentro de su bloque
     * (Objetivos/Controles/Planes/Gerencias/Ficha en riesgo/show). No tiene lógica
     * propia: delega en los métodos de abajo, que son los que autorizan y manejan
     * el voto de la doble validación.
     * Test: resolver_desde_la_tarjeta_pasa_por_la_autorizacion_de_cada_accion.
     */
    #[On('resolver-actualizacion')]
    public function resolver(int $id, string $accion): void
    {
        match ($accion) {
            'validar' => $this->validarActualizacion($id),
            'aprobar' => $this->aprobarActualizacion($id),
            'rechazar' => $this->rechazarActualizacion($id),
            'cancelar' => $this->cancelarActualizacion($id),
        };
    }

    /**
     * Valida el formulario y crea la Actualizacion: una nota si no trae cambios de
     * campo, o un cambio de campos (Actualizacion::registrarCambioCampos(), que
     * decide el estado inicial, la doble validación y si se aplica en el acto).
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

        $model = $this->resolverModelo();

        // respuesta/fundamento de un riesgo comparten la misma regla de negocio que
        // el form clásico (Riesgo::reglaRespuesta()/reglaFundamento()): obligatoria
        // siempre que se proponga, restringida según tipo_riesgo_id (el propuesto en
        // este mismo cambio si también se está tocando, si no el actual del riesgo),
        // y fundamento exigido si la respuesta elegida lo requiere.
        if ($this->modelType === 'riesgo') {
            if (($this->cambios['respuesta'] ?? '') !== '') {
                $tipoRiesgoId = ($this->cambios['tipo_riesgo_id'] ?? '') !== ''
                    ? $this->cambios['tipo_riesgo_id']
                    : $model->tipo_riesgo_id;
                // reglaRespuesta() empieza con 'required': acá no aplica porque el
                // campo es opcional (vacío = no se propone cambiarlo); se descarta esa
                // entrada y se deja el resto (enum + restricción por tipo de riesgo).
                $reglas['cambios.respuesta'] = array_slice(Riesgo::reglaRespuesta($tipoRiesgoId), 1);
            }
            if (($this->cambios['tipo_riesgo_id'] ?? '') !== '') {
                $reglas['cambios.tipo_riesgo_id'] = 'exists:tipos_riesgo,id';
            }
            $exigenFundamento = array_column(RespuestaRiesgo::exigenFundamento(), 'value');
            $reglas['cambios.fundamento'] = ['nullable', 'string', 'required_if:cambios.respuesta,'.implode(',', $exigenFundamento)];
        }

        $this->validate($reglas, [
            'cambios.*.date' => 'Ingresá una fecha válida.',
            'cambios.*.integer' => 'Ingresá un número entero.',
            'cambios.*.min' => 'El valor mínimo es :min.',
            'cambios.*.max' => 'El valor máximo es :max.',
            'cambios.respuesta.required' => 'Debe elegir una respuesta frente al riesgo.',
            'cambios.respuesta.not_in' => 'Un riesgo de este tipo no puede compartirse ni aceptarse como respuesta.',
            'cambios.fundamento.required_if' => 'Debe fundamentar por qué se eligió esta respuesta frente al riesgo.',
        ]);

        $campos = collect($this->cambios)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->toArray();

        $this->authorize('update', $model);

        if (empty($campos)) {
            $actualizacion = Actualizacion::registrarNota($model, Auth::user(), $this->mensaje);
        } else {
            $actualizacion = Actualizacion::registrarCambioCampos($model, Auth::user(), $this->mensaje, $campos);
            // Se avisa haya aplicado o no: si quedó como propuesta pendiente, en
            // riesgo/show la Ficha la muestra debajo de lo vigente.
            $this->dispatch("{$this->modelType}-actualizado");
        }

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
            // Aunque falten votos, la tarjeta de la propuesta tiene que mostrar el nuevo.
            $this->dispatch("{$this->modelType}-actualizado");

            return;
        }

        $actualizacion->marcarValidada(Auth::user());
        // marcarValidada() sólo aplica los cambios si la entidad ya estaba
        // "validado" (ver Actualizacion::marcarValidada) — se dispatcha igual sin
        // distinguir ese caso: un re-render de más en Info* no rompe nada.
        $this->dispatch("{$this->modelType}-actualizado");
    }

    public function cancelarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('cancelar', $actualizacion);

        $actualizacion->update(['estado_id' => Estado::borrado()->id]);
        // No cambia la entidad, pero la propuesta tiene que desaparecer de su bloque.
        $this->dispatch("{$this->modelType}-actualizado");
    }

    public function aprobarActualizacion(int $actualizacionId): void
    {
        $actualizacion = Actualizacion::findOrFail($actualizacionId);
        $this->authorize('aprobar', $actualizacion);
        $actualizacion->marcarAprobada(Auth::user());
        $this->dispatch("{$this->modelType}-actualizado");
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

        $actualizacion->marcarRechazada(Auth::user());
        $this->dispatch("{$this->modelType}-actualizado");
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
     * el componente podría llegar a escribir.
     */
    private function camposEditables(): array
    {
        return match ($this->modelType) {
            // Impacto y probabilidad se proponen igual que cualquier otro campo (mismo
            // ciclo de validación/doble-validación, ver camposNumericos()): corregir un
            // valor puntual no obliga a repetir las 5 preguntas del wizard de recálculo
            // (RiesgoController::recalcular()), que sigue existiendo sin cambios. Ver D-015.
            'riesgo' => [
                'nombre' => 'Nombre',
                'descripcion' => 'Descripción',
                'respuesta' => 'Respuesta',
                'fundamento' => 'Fundamento',
                'tipo_riesgo_id' => 'Tipo de riesgo',
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
     * impacto/probabilidad de Riesgo replican el tope 0-10 que ya rige la suma de las
     * 5 preguntas del wizard (cada una 0-2) — ver RiesgoController::store()/recalcularStore().
     */
    private function camposNumericos(): array
    {
        return [
            'mitigacion_default' => ['min' => 1, 'max' => 10],
            'porcentaje_avance' => ['min' => 0, 'max' => 100],
            'impacto' => ['min' => 0, 'max' => 10],
            'probabilidad' => ['min' => 0, 'max' => 10],
        ];
    }

    /**
     * Campos de `camposEditables()` que se renderizan como <select>, con sus
     * opciones (valor => etiqueta). `respuesta` y `tipo_riesgo_id` son los únicos
     * hoy, ambos exclusivos de Riesgo.
     */
    private function camposSelect(): array
    {
        if ($this->modelType !== 'riesgo') {
            return [];
        }

        return [
            'respuesta' => collect(RespuestaRiesgo::cases())
                ->mapWithKeys(fn ($caso) => [$caso->value => $caso->label()])
                ->toArray(),
            'tipo_riesgo_id' => TipoRiesgo::orderBy('nombre')->pluck('nombre', 'id')->toArray(),
        ];
    }

    public function render()
    {
        $actualizaciones = $this->resolverModelo()
            ->actualizaciones()
            ->with(['user.area', 'estado', 'media', 'validadoPor', 'aprobadoPor', 'rechazadoPor', 'validacionesGerencia.area', 'validacionesGerencia.user'])
            ->latest('created_at')
            ->latest('id')
            ->get();

        // La línea de tiempo es el historial de cambios: las notas sueltas viven en
        // la Conversación del riesgo (ConversacionRiesgo), no acá. El modal de
        // detalle sí las incluye (filtrables), por eso se guarda la lista entera.
        $todas = $actualizaciones;
        if ($this->variante === 'timeline') {
            $actualizaciones = $actualizaciones->reject(fn ($a) => $a->estado_id === null && empty($a->data))->values();
        }

        $vista = $this->variante === 'timeline'
            ? 'livewire.auditoria.actualizaciones.gestion-actualizaciones-timeline'
            : 'livewire.auditoria.actualizaciones.gestion-actualizaciones';

        return view($vista, [
            'actualizaciones' => $actualizaciones,
            'todas' => $todas,
            // La línea de tiempo marca las propuestas todavía sin aplicar (misma regla que los bloques).
            'pendientesIds' => $this->variante === 'timeline'
                ? $this->resolverModelo()->actualizaciones()->propuestasPendientes()->pluck('id')
                : collect(),
            'camposEditables' => $this->camposEditables(),
            'camposFecha' => $this->camposFecha(),
            'camposNumericos' => $this->camposNumericos(),
            'camposSelect' => $this->camposSelect(),
            // Resuelve tipo_riesgo_id → nombre en la entrada de creación de un riesgo
            // (una consulta liviana; para el resto de entidades queda vacío e inocuo).
            'tiposRiesgo' => TipoRiesgo::pluck('nombre', 'id'),
        ]);
    }
}
