<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
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
    /** @var array<string, array{modelo: class-string, label: string, prefijo: string}> */
    private const TIPOS = [
        'riesgo'   => ['modelo' => Riesgo::class,     'label' => 'Riesgos',         'prefijo' => 'auditoria.riesgos'],
        'control'  => ['modelo' => Control::class,    'label' => 'Controles',       'prefijo' => 'auditoria.controles'],
        'objetivo' => ['modelo' => Objetivo::class,   'label' => 'Objetivos',       'prefijo' => 'auditoria.objetivos'],
        'plan'     => ['modelo' => PlanAccion::class, 'label' => 'Planes de Acción','prefijo' => 'auditoria.planes'],
        'tarea'    => ['modelo' => Tarea::class,      'label' => 'Tareas',          'prefijo' => 'auditoria.tareas'],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->esGerente() || $user->esComite(), 403);

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

        return view('auditoria.pendiente.index', [
            'tipos'                      => self::TIPOS,
            'paraValidar'                => $paraValidar,
            'paraAprobar'                => $paraAprobar,
            'actualizacionesParaValidar' => $actualizacionesParaValidar,
            'actualizacionesParaAprobar' => $actualizacionesParaAprobar,
        ]);
    }

    private function buscar(string $modelo, string $estadoNombre, ?array $areaIds)
    {
        $query = $modelo::whereHas('estado', fn($q) => $q->where('nombre', $estadoNombre))
            ->with(['area', 'user']);

        if ($areaIds !== null) {
            $query->whereIn('area_id', $areaIds);
        }

        return $query->latest('created_at')->get();
    }

    private function buscarActualizaciones(string $estadoNombre, array $morphClases, ?array $areaIds)
    {
        $query = Actualizacion::whereHas('estado', fn($q) => $q->where('nombre', $estadoNombre))
            ->whereHasMorph('actualizable', $morphClases, function ($q) use ($areaIds) {
                if ($areaIds !== null) {
                    $q->whereIn('area_id', $areaIds);
                }
            })
            ->with(['actualizable.area', 'user']);

        return $query->latest('created_at')->get();
    }
}
