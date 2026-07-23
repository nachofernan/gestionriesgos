<?php

namespace App\Livewire\Auditoria;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Livewire\Component;

/**
 * Panel de situación de la matriz de riesgos: mapa de calor (impacto x
 * probabilidad), distribución total vs residual, KPIs numéricos y dos accesos
 * expandidos (Pendientes y Vencimientos). Es de sólo lectura: no muta nada, así
 * que la autorización acá es puramente el recorte de visibilidad.
 *
 * El sesgo es gerencial (ver Riesgo::scopeDeCascadaArea): un gerente ve sólo su
 * gerencia y sub-áreas; el comité ve todo. Nunca se muestran borradores (un
 * borrador es mono-gerencia y no debe salir de su origen ni siquiera dentro de la
 * gerencia) ni borrados. El toggle "solo aprobados" (por defecto encendido) acota
 * a lo aprobado; apagado suma también lo validado, nunca menos que eso.
 */
class PanelRiesgos extends Component
{
    /** Días de anticipación con los que una tarea se considera "por vencer". */
    private const DIAS_POR_VENCER = 30;

    /**
     * Toggle "ver solo aprobados". Encendido (default) el panel muestra sólo los
     * riesgos aprobados; apagado suma los validados. Los borradores nunca entran.
     */
    public bool $soloAprobados = true;

    public function render()
    {
        $user = auth()->user();

        $riesgos = $this->riesgosDelPanel($user);

        return view('livewire.auditoria.panel-riesgos', [
            'total' => $riesgos->count(),
            'celdas' => $this->celdasMatriz($riesgos),
            'riesgosJs' => $this->riesgosParaJs($riesgos),
            'distCriticidad' => $this->distribucionPorCriticidad($riesgos),
            'pistaInherente' => $this->distribucionPorValor($riesgos, 'valor_total'),
            'pistaResidual' => $this->distribucionPorValor($riesgos, 'valor_residual'),
            'exposicionTotal' => $riesgos->sum('valor_total'),
            'exposicionResidual' => $riesgos->sum('valor_residual'),
            'pendientes' => $this->resumenPendientes($user),
            'vencimientos' => $this->resumenVencimientos($user),
        ]);
    }

    /**
     * Los riesgos que entran al panel bajo el sesgo gerencial. Estados: nunca
     * "borrador" ni "borrado"; el toggle decide entre sólo "aprobado" (default) o
     * "aprobado" + "validado". Eager-load de lo que necesita el accessor
     * valor_residual (controles/planes aprobados) para no pegar N+1.
     */
    private function riesgosDelPanel(User $user)
    {
        $estados = $this->soloAprobados ? ['aprobado'] : ['aprobado', 'validado'];

        return Riesgo::deCascadaArea($user)
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', $estados))
            ->with(['estado', 'tipoRiesgo', 'controles.estado', 'planesAccion.tareas.estado'])
            ->get();
    }

    /**
     * Conteo de riesgos por celda de la matriz, indexado "impacto-probabilidad".
     * La posición es siempre el riesgo inherente (impacto/probabilidad crudos): el
     * residual no se puede ubicar en la grilla porque la mitigación baja la suma,
     * no las coordenadas. Por eso el residual se cuenta aparte, en la tira.
     */
    private function celdasMatriz($riesgos): array
    {
        return $riesgos
            ->groupBy(fn ($r) => $r->impacto.'-'.$r->probabilidad)
            ->map->count()
            ->all();
    }

    /** Riesgos aplanados para el detalle client-side al hacer clic en una celda. */
    private function riesgosParaJs($riesgos): array
    {
        return $riesgos->map(fn ($r) => [
            'celda' => $r->impacto.'-'.$r->probabilidad,
            'codigo' => $r->codigo,
            'nombre' => $r->nombre,
            'tipo' => $r->tipoRiesgo?->nombre,
            'estado' => $r->estado?->nombre,
            'total' => $r->valor_total,
            'residual' => $r->valor_residual,
            'clasificacion' => $r->clasificacion_residual['etiqueta'],
            'url' => route('auditoria.riesgos.show', $r),
        ])->values()->all();
    }

    /** Distribución bajo/moderado/crítico por valor residual (alimenta los KPIs). */
    private function distribucionPorCriticidad($riesgos): array
    {
        $base = ['bajo' => 0, 'moderado' => 0, 'critico' => 0];

        foreach ($riesgos as $r) {
            $base[$r->clasificacion_residual['etiqueta']]++;
        }

        return $base;
    }

    /**
     * Conteo de riesgos por cada valor posible (0..20) según el accessor dado
     * (valor_total o valor_residual). Alimenta los dos rieles de cubitos "antes /
     * después de mitigar": comparar ambas filas deja ver cómo los controles y
     * planes corren el panorama hacia la izquierda (valores más bajos).
     */
    private function distribucionPorValor($riesgos, string $accessor): array
    {
        $base = array_fill(0, 21, 0);

        foreach ($riesgos as $r) {
            $base[$r->{$accessor}]++;
        }

        return $base;
    }

    /**
     * Resumen de la card de Pendientes: cuántas entidades y actualizaciones tiene
     * el usuario para validar (gerente, sobre borradores de su cascada) o aprobar
     * (comité, sobre validados). Espeja las condiciones de PendienteController;
     * acá sólo cuenta, el detalle vive en esa pantalla. Empleado no ve la card.
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
            'entidades' => $entidades,
            'actualizaciones' => $actualizaciones,
            'total' => $entidades + $actualizaciones,
        ];
    }

    /**
     * Resumen de la card de Vencimientos: tareas comprometidas (validado/aprobado,
     * < 100%, con fecha) de la cascada del usuario, con el conteo de vencidas y por
     * vencer y las más próximas. Misma consulta que VencimientoController.
     */
    private function resumenVencimientos(User $user): array
    {
        $hoy = today();
        $limite = $hoy->copy()->addDays(self::DIAS_POR_VENCER);

        $tareas = Tarea::query()
            ->when($user->area_id, fn ($q) => $q->whereIn('area_id', $user->idsAreasGestionables()))
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', ['validado', 'aprobado']))
            ->where('porcentaje_avance', '<', 100)
            ->whereNotNull('fecha')
            ->with(['estado', 'planesAccion'])
            ->orderBy('fecha')
            ->get();

        return [
            'vencidas' => $tareas->filter(fn ($t) => $t->fecha->lt($hoy))->count(),
            'porVencer' => $tareas->filter(fn ($t) => $t->fecha->gte($hoy) && $t->fecha->lte($limite))->count(),
            'proximas' => $tareas->take(6),
            'hoy' => $hoy,
        ];
    }
}
