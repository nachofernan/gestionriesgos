<?php

namespace App\Livewire\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use App\Services\Auditoria\Reportes\FiltrosReporte;
use App\Services\Auditoria\Reportes\ReporteEvolucion;
use App\Services\Auditoria\Reportes\ReportePlanes;
use App\Services\Auditoria\Reportes\ReportePorGerencia;
use App\Services\Auditoria\Reportes\ReportePorTipo;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Panel de Riesgos: hub de situación y reportes (D-033), en pestañas internas.
 * "Resumen" muestra los KPIs, los accesos a Pendientes y Vencimientos y la matriz
 * de calor; las otras cuatro son los reportes de app/Services/Auditoria/Reportes,
 * que también se descargan en Excel (ReporteController) con los mismos filtros.
 * Es de sólo lectura: la autorización es el recorte de visibilidad.
 *
 * El sesgo es gerencial (ver Riesgo::scopeDeCascadaArea): un gerente ve sólo su
 * gerencia y sub-áreas; el comité ve todo. Nunca se muestran borradores ni
 * borrados. El toggle "solo aprobados" (por defecto encendido) acota a lo
 * aprobado; apagado suma también lo validado.
 */
class PanelRiesgos extends Component
{
    public const PESTANAS = [
        'resumen' => 'Resumen',
        'gerencia' => 'Por gerencia',
        'tipo' => 'Por tipo',
        'evolucion' => 'Evolución',
        'planes' => 'Planes y tareas',
    ];

    /** Días de anticipación con los que una tarea se considera "por vencer". */
    private const DIAS_POR_VENCER = 30;

    #[Url]
    public string $pestana = 'resumen';

    public bool $soloAprobados = true;

    /**
     * Filtros por tipo (id de TipoRiesgo) y respuesta (valor del enum). String
     * sin tipar a propósito: el <select> de "Todos" manda "" y un property tipado
     * revienta el hydrate de Livewire con esa cadena vacía. "" = sin filtro.
     */
    public string $filtroTipo = '';

    public string $filtroRespuesta = '';

    /** Rango de la pestaña Evolución, como Y-m-d (lo que manda el input date). */
    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    public function mount(): void
    {
        if (! array_key_exists($this->pestana, self::PESTANAS)) {
            $this->pestana = 'resumen';
        }
        $filtros = new FiltrosReporte;
        $this->desde = $this->desde ?: $filtros->desde->toDateString();
        $this->hasta = $this->hasta ?: $filtros->hasta->toDateString();
    }

    public function limpiarFiltros(): void
    {
        $this->filtroTipo = '';
        $this->filtroRespuesta = '';
    }

    /** Filtros actuales, en la forma que consumen los reportes y la descarga. */
    public function filtros(): FiltrosReporte
    {
        return FiltrosReporte::desdeArray([
            'soloAprobados' => $this->soloAprobados,
            'tipo' => $this->filtroTipo,
            'respuesta' => $this->filtroRespuesta,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ]);
    }

    public function render()
    {
        $user = auth()->user();
        $filtros = $this->filtros();

        $datos = match ($this->pestana) {
            'gerencia' => ['reporte' => (new ReportePorGerencia($user, $filtros))->datos()],
            'tipo' => ['reporte' => (new ReportePorTipo($user, $filtros))->datos()],
            'evolucion' => ['reporte' => (new ReporteEvolucion($user, $filtros))->datos()],
            'planes' => ['reporte' => (new ReportePlanes($user, $filtros))->datos()],
            default => $this->datosResumen($user, $filtros),
        };

        return view('livewire.auditoria.panel-riesgos', $datos + [
            'pestanas' => self::PESTANAS,
            'urlExcel' => $this->pestana === 'resumen'
                ? null
                : route('auditoria.panel.reporte', ['reporte' => $this->pestana] + $filtros->aArray()),
            'tipos' => TipoRiesgo::orderBy('nombre')->get(),
            'respuestas' => RespuestaRiesgo::cases(),
        ]);
    }

    /**
     * KPIs (los totales del reporte por gerencia: misma fuente que el resto), la
     * matriz de calor y los accesos a Pendientes y Vencimientos.
     */
    private function datosResumen(User $user, FiltrosReporte $filtros): array
    {
        $riesgos = $this->riesgosDelPanel($user);

        return [
            'total' => $riesgos->count(),
            'kpis' => (new ReportePorGerencia($user, $filtros))->datos()['totales'],
            'celdas' => $this->celdasMatriz($riesgos),
            'celdasResidual' => $this->celdasMatrizResidual($riesgos),
            'pendientes' => $this->resumenPendientes($user),
            'vencimientos' => $this->resumenVencimientos($user),
        ];
    }

    /**
     * Los riesgos que entran a la matriz bajo el sesgo gerencial, con lo que
     * necesita el accessor valor_residual eager-loaded.
     */
    private function riesgosDelPanel(User $user)
    {
        $estados = $this->soloAprobados ? ['aprobado'] : ['aprobado', 'validado'];

        return Riesgo::deCascadaArea($user)
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', $estados))
            ->when($this->filtroTipo !== '', fn ($q) => $q->where('tipo_riesgo_id', (int) $this->filtroTipo))
            ->when($this->filtroRespuesta !== '', fn ($q) => $q->where('respuesta', $this->filtroRespuesta))
            ->with(['estado', 'tipoRiesgo', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->get();
    }

    /**
     * Conteo de riesgos por celda de la matriz, indexado "impacto-probabilidad".
     * La posición es siempre el riesgo inherente (impacto/probabilidad crudos).
     */
    private function celdasMatriz($riesgos): array
    {
        return $riesgos
            ->groupBy(fn ($r) => $r->impacto.'-'.$r->probabilidad)
            ->map->count()
            ->all();
    }

    /**
     * Conteo de riesgos por celda, ubicados según un reparto proporcional de la
     * mitigación entre impacto y probabilidad. Sólo una simulación visual: ver
     * repartoProporcional().
     */
    private function celdasMatrizResidual($riesgos): array
    {
        return $riesgos
            ->groupBy(function ($r) {
                [$impacto, $probabilidad] = $this->repartoProporcional($r);

                return $impacto.'-'.$probabilidad;
            })
            ->map->count()
            ->all();
    }

    /**
     * Reparte la mitigación total (valor_total - valor_residual) proporcionalmente
     * entre impacto y probabilidad, para ubicar el riesgo "después de mitigar" en
     * la misma grilla que el inherente. Si el redondeo no suma exacto el
     * valor_residual, el ajuste se absorbe en el eje de mayor peso. Puramente
     * visual: el cálculo de negocio sigue siendo Riesgo::getValorResidualAttribute.
     */
    private function repartoProporcional(Riesgo $r): array
    {
        $total = $r->valor_total;

        if ($total === 0) {
            return [0, 0];
        }

        $residual = $r->valor_residual;
        $impacto = (int) round($r->impacto * $residual / $total);
        $probabilidad = (int) round($r->probabilidad * $residual / $total);

        $ajuste = $residual - ($impacto + $probabilidad);
        if ($ajuste !== 0) {
            if ($r->impacto >= $r->probabilidad) {
                $impacto += $ajuste;
            } else {
                $probabilidad += $ajuste;
            }
        }

        return [max(0, $impacto), max(0, $probabilidad)];
    }

    /**
     * Cuántas entidades y actualizaciones tiene el usuario para validar (gerente,
     * sobre borradores de su cascada) o aprobar (comité, sobre validados). Espeja
     * las condiciones de PendienteController. Empleado no lo ve.
     */
    private function resumenPendientes(User $user): array
    {
        if (! ($user->esGerente() || $user->esComite())) {
            return ['mostrar' => false];
        }

        $areaIds = $user->area_id ? $user->area->obtenerIdsSubarbol() : null;
        $estado = $user->esComite() ? 'validado' : 'borrador';
        $modelos = [Riesgo::class, Control::class, Objetivo::class, PlanAccion::class, Tarea::class];

        $entidades = 0;
        foreach ($modelos as $modelo) {
            $query = $modelo::whereHas('estado', fn ($q) => $q->where('nombre', $estado));
            if ($areaIds !== null) {
                $query->whereIn('area_id', $areaIds);
            }
            $entidades += $query->count();
        }

        $actualizaciones = Actualizacion::whereHas('estado', fn ($q) => $q->where('nombre', $estado))
            ->whereHasMorph('actualizable', $modelos, function ($q) use ($areaIds) {
                if ($areaIds !== null) {
                    $q->whereIn('area_id', $areaIds);
                }
            })
            ->count();

        return [
            'mostrar' => true,
            'accion' => $user->esComite() ? 'aprobar' : 'validar',
            'total' => $entidades + $actualizaciones,
        ];
    }

    /**
     * Tareas comprometidas (validado/aprobado, < 100%, con fecha) de la cascada
     * del usuario: vencidas y por vencer. Misma consulta que VencimientoController.
     */
    private function resumenVencimientos(User $user): array
    {
        $hoy = today();
        $limite = $hoy->copy()->addDays(self::DIAS_POR_VENCER);

        $fechas = Tarea::query()
            ->when($user->area_id, fn ($q) => $q->whereIn('area_id', $user->idsAreasGestionables()))
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', ['validado', 'aprobado']))
            ->where('porcentaje_avance', '<', 100)
            ->whereNotNull('fecha')
            ->pluck('fecha');

        return [
            'vencidas' => $fechas->filter(fn ($f) => $f->lt($hoy))->count(),
            'porVencer' => $fechas->filter(fn ($f) => $f->gte($hoy) && $f->lte($limite))->count(),
        ];
    }
}
