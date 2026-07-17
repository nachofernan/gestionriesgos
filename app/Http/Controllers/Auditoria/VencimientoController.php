<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Tarea;
use Illuminate\Http\Request;

/**
 * Pantalla "Vencimientos": las Tareas comprometidas del usuario ordenadas por
 * fecha, para ver de un vistazo qué está vencido y qué está por vencer.
 *
 * Sólo las Tarea tienen fecha en el esquema; los Plan de Acción no, así que
 * cada fila es una tarea y muestra a qué plan(es) pertenece. Quedan afuera las
 * tareas terminadas (avance 100: ya no hay nada que reclamar) y las que no
 * llegaron a validado (un borrador todavía no es un compromiso con fecha).
 */
class VencimientoController extends Controller
{
    /** Días de anticipación con los que una tarea se marca "por vencer". */
    private const DIAS_POR_VENCER = 30;

    public function index(Request $request)
    {
        $hoy = today();
        $limite = $hoy->copy()->addDays(self::DIAS_POR_VENCER);

        $tareas = Tarea::visiblePara($request->user())
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', ['validado', 'aprobado']))
            ->where('porcentaje_avance', '<', 100)
            ->with(['planesAccion', 'estado', 'area', 'user'])
            ->orderByRaw('fecha is null')
            ->orderBy('fecha')
            ->get();

        $conFecha = $tareas->whereNotNull('fecha');

        return view('auditoria.vencimiento.index', [
            'vencidas' => $conFecha->filter(fn ($t) => $t->fecha->lt($hoy))->values(),
            'porVencer' => $conFecha->filter(fn ($t) => $t->fecha->gte($hoy) && $t->fecha->lte($limite))->values(),
            'enPlazo' => $conFecha->filter(fn ($t) => $t->fecha->gt($limite))->values(),
            'sinFecha' => $tareas->whereNull('fecha')->values(),
            'hoy' => $hoy,
            'diasPorVencer' => self::DIAS_POR_VENCER,
        ]);
    }
}
