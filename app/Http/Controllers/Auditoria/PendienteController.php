<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Pantalla "Pendientes": todo lo que el usuario logueado tiene que validar
 * (entidades/Actualizaciones en borrador de su área) o aprobar (en validado,
 * sin restricción de área). Las condiciones espejan literalmente las de
 * RiesgoPolicy/ActualizacionPolicy (y análogas) en vez de reinventarlas, para
 * no desincronizarse de quién puede hacer qué.
 */
class PendienteController extends Controller
{
    /** @var array<string, array{modelo: class-string, label: string, singular: string, prefijo: string}> */
    private const TIPOS = [
        'riesgo' => ['modelo' => Riesgo::class,     'label' => 'Riesgos',         'singular' => 'Riesgo',         'prefijo' => 'auditoria.riesgos'],
        'control' => ['modelo' => Control::class,    'label' => 'Controles',       'singular' => 'Control',        'prefijo' => 'auditoria.controles'],
        'objetivo' => ['modelo' => Objetivo::class,   'label' => 'Objetivos',       'singular' => 'Objetivo',       'prefijo' => 'auditoria.objetivos'],
        'plan' => ['modelo' => PlanAccion::class, 'label' => 'Planes de Acción', 'singular' => 'Plan de acción', 'prefijo' => 'auditoria.planes'],
        'tarea' => ['modelo' => Tarea::class,      'label' => 'Tareas',          'singular' => 'Tarea',          'prefijo' => 'auditoria.tareas'],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->esGerente() || $user->esComite(), 403);

        $datos = $this->datosPendientes($user);

        return view('auditoria.pendiente.index', $datos + ['detalles' => $this->detalles($user, $datos)]);
    }

    /**
     * Genera el mismo listado de index() en PDF, para llevar impreso a una
     * reunión. Sin acciones ni interactividad, sólo lectura.
     */
    public function pdf(Request $request)
    {
        $user = $request->user();
        abort_unless($user->esGerente() || $user->esComite(), 403);

        $pdf = Pdf::loadView('auditoria.pendiente.pdf', $this->datosPendientes($user) + ['user' => $user]);

        return $pdf->download('pendientes-'.now()->format('Y-m-d').'.pdf');
    }

    private function datosPendientes($user): array
    {
        // null = sin restricción de área (comité, o cualquier usuario sin área propia)
        $areaIds = $user->area_id ? $user->area->obtenerIdsSubarbol() : null;

        $paraValidar = [];
        $paraAprobar = [];

        foreach (self::TIPOS as $tipo => $cfg) {
            if ($user->esGerente()) {
                $paraValidar[$tipo] = $this->buscar($cfg['modelo'], 'borrador', $areaIds);
            }
            if ($user->esComite()) {
                $paraAprobar[$tipo] = $this->buscar($cfg['modelo'], 'validado', $areaIds);
            }
        }

        $morphClases = array_column(self::TIPOS, 'modelo');

        $actualizacionesParaValidar = $user->esGerente()
            ? $this->buscarActualizaciones('borrador', $morphClases, $areaIds)
            : collect();

        $actualizacionesParaAprobar = $user->esComite()
            ? $this->buscarActualizaciones('validado', $morphClases, $areaIds)
            : collect();

        return [
            'tipos' => self::TIPOS,
            'paraValidar' => $paraValidar,
            'paraAprobar' => $paraAprobar,
            'actualizacionesParaValidar' => $actualizacionesParaValidar,
            'actualizacionesParaAprobar' => $actualizacionesParaAprobar,
        ];
    }

    private function buscar(string $modelo, string $estadoNombre, ?array $areaIds)
    {
        $query = $modelo::whereHas('estado', fn ($q) => $q->where('nombre', $estadoNombre))
            ->with(['area', 'user', 'estado']);

        if ($areaIds !== null) {
            $query->whereIn('area_id', $areaIds);
        }

        return $query->latest('created_at')->get();
    }

    private function buscarActualizaciones(string $estadoNombre, array $morphClases, ?array $areaIds)
    {
        // Sólo propuestas ('cambio'): los registros de historial (creacion, edicion…)
        // también nacen en borrador y se sellan a validado junto con su elemento,
        // pero ya están aplicados y no hay nada que decidir sobre ellos.
        // Test: los_registros_de_historial_no_aparecen_como_cambios_propuestos_ni_antes_ni_despues_de_validar.
        $query = Actualizacion::where('data->tipo', 'cambio')
            ->whereHas('estado', fn ($q) => $q->where('nombre', $estadoNombre))
            ->whereHasMorph('actualizable', $morphClases, function ($q) use ($areaIds) {
                if ($areaIds !== null) {
                    $q->whereIn('area_id', $areaIds);
                }
            })
            ->with(['actualizable.area', 'actualizable.estado', 'user', 'estado']);

        return $query->latest('created_at')->get();
    }

    /**
     * Payload del modal de vista rápida de cada fila, indexado por
     * "{tipo}-{id}" (entidades) o "actualizacion-{id}" (propuestas). Sólo lo usa
     * index(): la vista lo embebe con @js y el modal (Alpine) lo pinta sin volver
     * al servidor. Los permisos (puede_ver, puede_accion, puede_rechazar) se
     * resuelven acá contra las policies; los botones del modal sólo disparan los
     * mismos flujos de la fila, que vuelven a autorizar al mutar.
     * Test: el_modal_de_una_propuesta_trae_el_diff_legible_y_los_permisos.
     */
    private function detalles($user, array $datos): array
    {
        $detalles = [];

        foreach (['validar' => $datos['paraValidar'], 'aprobar' => $datos['paraAprobar']] as $accion => $porTipo) {
            foreach ($porTipo as $tipo => $items) {
                $items->loadMissing(match ($tipo) {
                    'riesgo' => ['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'],
                    'plan' => ['tareas.estado'],
                    'tarea' => ['planesAccion'],
                    default => [],
                });

                foreach ($items as $item) {
                    $detalles["{$tipo}-{$item->id}"] = $this->detalleEntidad($user, $tipo, $item, $accion);
                }
            }
        }

        foreach (['validar' => $datos['actualizacionesParaValidar'], 'aprobar' => $datos['actualizacionesParaAprobar']] as $accion => $actualizaciones) {
            foreach ($actualizaciones as $actualizacion) {
                $detalles["actualizacion-{$actualizacion->id}"] = $this->detallePropuesta($user, $actualizacion, $accion);
            }
        }

        return $detalles;
    }

    private function detalleEntidad($user, string $tipo, $item, string $accion): array
    {
        $cfg = self::TIPOS[$tipo];

        return [
            'modo' => 'entidad',
            'clave' => "{$tipo}-{$item->id}",
            'tipo' => $tipo,
            'id' => $item->id,
            'tipo_label' => $cfg['singular'],
            'nombre' => $item->nombre,
            'descripcion' => $item->descripcion,
            'area' => $item->area?->nombre ?? 'Sin área',
            'propietario' => $item->user?->name,
            'estado_nombre' => $item->estado?->nombre ?? 'borrador',
            'estado_color' => $item->estado?->color ?? 'gray',
            'datos' => $this->datosEntidad($tipo, $item),
            'url' => route($cfg['prefijo'].'.show', $item),
            'url_rechazar' => route($cfg['prefijo'].'.rechazar', $item),
            'puede_ver' => $user->can('view', $item),
            'accion' => $accion,
            'puede_accion' => $user->can($accion, $item),
            'puede_rechazar' => $user->can('rechazar', $item),
        ];
    }

    /**
     * Filas [etiqueta, valor, color?] con lo que hace falta para decidir según el
     * tipo. Los valores del riesgo salen de sus accessors (valor_total /
     * valor_residual), nunca recalculados acá.
     */
    private function datosEntidad(string $tipo, $item): array
    {
        $color = fn (array $clasificacion) => ['verde' => 'green', 'amarillo' => 'amber', 'rojo' => 'red'][$clasificacion['color']] ?? null;

        return match ($tipo) {
            'riesgo' => [
                ['etiqueta' => 'Impacto', 'valor' => $item->impacto],
                ['etiqueta' => 'Probabilidad', 'valor' => $item->probabilidad],
                ['etiqueta' => 'Valor total', 'valor' => $item->valor_total.' · '.$item->clasificacion_total['etiqueta'], 'color' => $color($item->clasificacion_total)],
                ['etiqueta' => 'Valor residual', 'valor' => $item->valor_residual.' · '.$item->clasificacion_residual['etiqueta'], 'color' => $color($item->clasificacion_residual)],
                ['etiqueta' => 'Respuesta', 'valor' => $item->respuesta?->label() ?? '—'],
                ['etiqueta' => 'Controles', 'valor' => $item->controles->count()],
                ['etiqueta' => 'Planes de acción', 'valor' => $item->planesAccion->count()],
            ],
            'control' => [
                ['etiqueta' => 'Mitigación por defecto', 'valor' => $item->mitigacion_default],
                ['etiqueta' => 'Pausado', 'valor' => $item->pausado ? 'Sí' : 'No'],
            ],
            'objetivo' => [
                ['etiqueta' => 'Fecha objetivo', 'valor' => $item->fecha_objetivo?->format('d/m/Y') ?? '—'],
            ],
            'plan' => [
                ['etiqueta' => 'Avance', 'valor' => $item->avance !== null ? $item->avance.'%' : 'Sin tareas aprobadas'],
            ],
            'tarea' => [
                ['etiqueta' => 'Fecha límite', 'valor' => $item->fecha?->format('d/m/Y') ?? '—'],
                ['etiqueta' => 'Plan de acción', 'valor' => $item->planesAccion->pluck('nombre')->join(', ') ?: '—'],
            ],
        };
    }

    private function detallePropuesta($user, Actualizacion $actualizacion, string $accion): array
    {
        $entidad = $actualizacion->actualizable;
        $tipo = $entidad ? collect(self::TIPOS)->search(fn ($cfg) => $entidad instanceof $cfg['modelo']) : null;
        $tipo = $tipo ?: null;
        $data = $actualizacion->data ?? [];

        $votos = null;
        if ($actualizacion->estado?->nombre === 'borrador' && $actualizacion->requiereDobleValidacion()) {
            $votos = [
                'a_favor' => $actualizacion->validacionesGerencia()->where('aprueba', true)->with('area')->get()->pluck('area.nombre')->all(),
                'faltan' => $actualizacion->gerenciasPendientes()->all(),
            ];
        }

        return [
            'modo' => 'propuesta',
            'clave' => "actualizacion-{$actualizacion->id}",
            'id' => $actualizacion->id,
            'tipo_label' => $tipo ? self::TIPOS[$tipo]['singular'] : 'Elemento',
            'nombre' => $entidad?->nombre ?? '(elemento eliminado)',
            'area' => $entidad?->area?->nombre ?? 'Sin área',
            'propietario' => $actualizacion->user?->name,
            'fecha' => $actualizacion->created_at?->diffForHumans(),
            'fecha_completa' => $actualizacion->created_at?->format('d/m/Y H:i'),
            'situacion' => $actualizacion->estado?->nombre === 'validado' ? 'Espera al comité' : 'Espera validación',
            'votos' => $votos,
            'mensaje' => $actualizacion->mensaje,
            'diff' => $actualizacion->diffLegible(),
            'propagar_mitigacion' => ! empty($data['propagar_mitigacion']),
            'url' => $tipo ? route(self::TIPOS[$tipo]['prefijo'].'.show', $entidad) : null,
            'puede_ver' => $entidad !== null && $user->can('view', $entidad),
            'accion' => $accion,
            'puede_accion' => $user->can($accion, $actualizacion),
            'puede_rechazar' => $user->can('rechazar', $actualizacion),
        ];
    }
}
