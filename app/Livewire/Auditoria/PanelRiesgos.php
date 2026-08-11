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

    /**
     * Filtro por tipo de riesgo (id de TipoRiesgo) y por respuesta (valor del
     * enum RespuestaRiesgo). String sin tipar a propósito: el <select> de "Todos"
     * manda "" y un property tipado (?int/?string) revienta el hydrate de
     * Livewire con esa cadena vacía. "" es la marca de "sin filtro".
     */
    public string $filtroTipo = '';

    public string $filtroRespuesta = '';

    /** Limpia ambos filtros de una sola pasada (botón "Limpiar filtros"). */
    public function limpiarFiltros(): void
    {
        $this->filtroTipo = '';
        $this->filtroRespuesta = '';
    }

    public function render()
    {
        $user = auth()->user();

        $riesgos = $this->riesgosDelPanel($user);

        return view('livewire.auditoria.panel-riesgos', [
            'total' => $riesgos->count(),
            'celdas' => $this->celdasMatriz($riesgos),
            'celdasResidual' => $this->celdasMatrizResidual($riesgos),
            'riesgosJs' => $this->riesgosParaJs($riesgos),
            'distCriticidad' => $this->distribucionPorCriticidad($riesgos),
            'pistaInherente' => $this->distribucionPorValor($riesgos, 'valor_total'),
            'pistaResidual' => $this->distribucionPorValor($riesgos, 'valor_residual'),
            'exposicionTotal' => $riesgos->sum('valor_total'),
            'exposicionResidual' => $riesgos->sum('valor_residual'),
            'pendientes' => $this->resumenPendientes($user),
            'vencimientos' => $this->resumenVencimientos($user),
            'tipos' => TipoRiesgo::orderBy('nombre')->get(),
            'respuestas' => RespuestaRiesgo::cases(),
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
            ->when($this->filtroTipo !== '', fn ($q) => $q->where('tipo_riesgo_id', (int) $this->filtroTipo))
            ->when($this->filtroRespuesta !== '', fn ($q) => $q->where('respuesta', $this->filtroRespuesta))
            ->with(['estado', 'tipoRiesgo', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
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

    /**
     * Conteo de riesgos por celda de la matriz, pero ubicados según un reparto
     * proporcional de la mitigación entre impacto y probabilidad (el valor
     * residual no distingue entre ambos ejes). Es sólo una simulación visual:
     * ver repartoProporcional().
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
     * entre impacto y probabilidad, para poder ubicar el riesgo "después de
     * mitigar" en la misma grilla 2D que el inherente. Cada eje se redondea al
     * entero más cercano; si el redondeo independiente no suma exacto el
     * valor_residual (puede pasar por ±1), el ajuste se absorbe en el eje de
     * mayor peso (impacto en empate). Puramente visual: no es el cálculo de
     * negocio, que sigue siendo Riesgo::getValorResidualAttribute.
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
            'controles' => $this->controlesParaJs($r),
            'planes' => $this->planesParaJs($r),
        ])->values()->all();
    }

    /**
     * Controles del riesgo en estado aprobado, con su aporte a la mitigación,
     * para explicar en la vista rápida por qué el residual bajó del total. Un
     * control en borrador/validado/borrado no mitiga (ver
     * Riesgo::getValorResidualAttribute) y por eso ni se lista: mostrarlo ahí
     * sugeriría un descuento que no existe.
     */
    private function controlesParaJs(Riesgo $r): array
    {
        return $r->controles
            ->filter(fn ($c) => $c->estado?->nombre === 'aprobado')
            ->map(fn ($c) => [
                'nombre' => $c->nombre,
                'mitigacion' => $c->pivot->mitigacion ?? $c->mitigacion_default,
            ])->values()->all();
    }

    /**
     * Planes de acción del riesgo en estado aprobado, con su avance y aporte a
     * la mitigación. Un plan en borrador/validado/borrado no puede mitigar
     * (ver Riesgo::getValorResidualAttribute) y no se lista. Entre los
     * aprobados, sólo "aporta" el que está al 100% de avance; se listan igual
     * los incompletos para que se entienda que todavía no descuentan.
     */
    private function planesParaJs(Riesgo $r): array
    {
        return $r->planesAccion
            ->filter(fn ($p) => $p->estado?->nombre === 'aprobado')
            ->map(fn ($p) => [
                'nombre' => $p->nombre,
                'avance' => $p->avance,
                'aporta' => $p->estaCompleto(),
                'mitigacion' => $p->pivot->mitigacion ?? 0,
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
