<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Illuminate\Http\Request;

/**
 * Borrador interno (D-044): el panel propio del área con lo que gestiona fuera del
 * circuito del comité. El flag se pone o se saca en la edición del borrador.
 */
class InternoController extends Controller
{
    private const DIAS_POR_VENCER = 30;

    /**
     * Panel interno: los borradores internos de las áreas que el usuario gestiona,
     * con el residual y el avance proyectados (Riesgo::getValorResidualProyectadoAttribute(),
     * PlanAccion::getAvanceProyectadoAttribute()) y los vencimientos de sus tareas.
     * El comité no tiene lo interno de nadie, así que no entra.
     * Tests: el_panel_interno_muestra_lo_interno_de_la_cascada,
     * el_comite_no_entra_al_panel_interno.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user->esComite(), 403);

        $areaIds = $user->idsAreasGestionables();
        $internos = fn (string $modelo) => $modelo::query()
            ->where('interno', true)
            ->where('estado_id', Estado::borrador()->id)
            ->whereIn('area_id', $areaIds);

        $riesgos = $internos(Riesgo::class)
            ->with(['area', 'tipoRiesgo', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->get()
            ->sortByDesc('valor_residual_proyectado')
            ->values();

        $planes = $internos(PlanAccion::class)
            ->with(['area', 'tareas.estado'])
            ->withCount('riesgos')
            ->orderBy('nombre')
            ->get();

        $tareas = $internos(Tarea::class)
            ->where('porcentaje_avance', '<', 100)
            ->with(['area', 'planesAccion'])
            ->orderByRaw('fecha is null')
            ->orderBy('fecha')
            ->get();

        $hoy = today();
        $limite = $hoy->copy()->addDays(self::DIAS_POR_VENCER);
        $conFecha = $tareas->whereNotNull('fecha');

        return view('auditoria.interno.index', [
            'riesgos' => $riesgos,
            'planes' => $planes,
            'cantidadControles' => $internos(Control::class)->count(),
            'cantidadObjetivos' => $internos(Objetivo::class)->count(),
            'vencimientos' => [
                'Vencidas' => $conFecha->filter(fn ($t) => $t->fecha->lt($hoy))->values(),
                'Por vencer' => $conFecha->filter(fn ($t) => $t->fecha->gte($hoy) && $t->fecha->lte($limite))->values(),
                'En plazo' => $conFecha->filter(fn ($t) => $t->fecha->gt($limite))->values(),
                'Sin fecha' => $tareas->whereNull('fecha')->values(),
            ],
            'diasPorVencer' => self::DIAS_POR_VENCER,
        ]);
    }
}
