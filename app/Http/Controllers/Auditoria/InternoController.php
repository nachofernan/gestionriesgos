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
 * circuito del comité, y el atajo de la ficha para poner o quitar el flag.
 */
class InternoController extends Controller
{
    /** @var array<string, array{modelo: class-string, ruta: string}> */
    private const TIPOS = [
        'riesgo' => ['modelo' => Riesgo::class, 'ruta' => 'auditoria.riesgos.show'],
        'control' => ['modelo' => Control::class, 'ruta' => 'auditoria.controles.show'],
        'objetivo' => ['modelo' => Objetivo::class, 'ruta' => 'auditoria.objetivos.show'],
        'plan' => ['modelo' => PlanAccion::class, 'ruta' => 'auditoria.planes.show'],
        'tarea' => ['modelo' => Tarea::class, 'ruta' => 'auditoria.tareas.show'],
    ];

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

    /**
     * Pone o quita el flag desde la ficha. Lo puede hacer quien edita el borrador
     * (view y update de la Policy): quitarlo no valida nada, sólo lo deja en la bandeja del
     * gerente. Fuera de borrador el flag no existe. Queda como una edición más del
     * borrador en el historial.
     * Tests: quien_edita_el_borrador_alterna_el_flag_y_queda_en_el_historial,
     * quien_no_gestiona_el_area_no_alterna_el_flag, fuera_de_borrador_no_se_alterna_el_flag.
     */
    public function alternar(Request $request, string $tipo, int $id)
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);
        ['modelo' => $modelo, 'ruta' => $ruta] = self::TIPOS[$tipo];

        $entidad = $modelo::findOrFail($id);
        // view además de update: el comité gestiona cualquier área pero no ve los
        // borradores ajenos, y lo interno no es asunto suyo.
        $this->authorize('view', $entidad);
        $this->authorize('update', $entidad);

        if ($entidad->estado?->nombre !== 'borrador') {
            return redirect()->route($ruta, $entidad)
                ->with('error', 'Sólo un borrador puede ser interno.');
        }

        $antes = $entidad->interno;
        $entidad->update(['interno' => ! $antes]);
        $entidad->actualizaciones()->create([
            'user_id' => $request->user()->id,
            'mensaje' => $antes ? 'Se quitó la marca de interno' : 'Marcado como interno',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'edicion', 'diff' => ['campos' => [
                'interno' => ['antes' => $antes, 'despues' => ! $antes],
            ]]],
        ]);

        return redirect()->route($ruta, $entidad)->with('ok', $antes
            ? 'Ya no es interno: queda en borrador, listo para que lo valide el gerente.'
            : 'Marcado como interno: sale de Pendientes y queda como gestión propia del área.');
    }
}
