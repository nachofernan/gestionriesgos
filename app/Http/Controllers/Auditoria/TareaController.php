<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de Tarea más su ciclo de vida de estados: borrador → validado → aprobado,
 * o borrador/validado → borrado (rechazo). Una tarea validada ya no se edita acá;
 * los cambios posteriores pasan por el sistema de Actualizaciones (ver también
 * ActualizacionTareaController para el registro rápido de avance).
 */
class TareaController extends Controller
{
    public function index()
    {
        $tareas = Tarea::with(['planesAccion.riesgos', 'user', 'area'])
            ->visiblePara(Auth::user())
            ->join('estados', 'estados.id', '=', 'tareas.estado_id')
            ->select('tareas.*')
            ->orderByRaw(Estado::ordenSql())
            ->orderByDesc('tareas.created_at')
            ->paginate(20);

        return view('auditoria.tarea.index', compact('tareas'));
    }

    public function create()
    {
        $areas = Area::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('auditoria.tarea.create', compact('areas', 'usuarios'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', [Tarea::class, $request->input('area_id')]);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'porcentaje_avance' => 'required|integer|min:0|max:100',
            'fecha' => 'nullable|date',
            'area_id' => 'nullable|exists:areas,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $data['user_id'] = $data['user_id'] ?? Auth::id();
        $tarea = Tarea::create($data);
        $tarea->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Tarea creada',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'creacion', 'campos' => [
                'nombre' => $tarea->nombre,
                'descripcion' => $tarea->descripcion,
                'porcentaje_avance' => $tarea->porcentaje_avance,
                'fecha' => $tarea->fecha?->format('Y-m-d'),
            ]],
        ]);

        return redirect()->route('auditoria.tareas.index')->with('ok', 'Tarea creada.');
    }

    public function show(Tarea $tarea)
    {
        $this->authorize('view', $tarea);
        // planesAccion.riesgos filtrados por visibilidad: un borrador de otra
        // gerencia no debe aparecer como chip bajo su plan (axioma 3 + scopeVisiblePara).
        $tarea->load([
            'planesAccion.estado', 'planesAccion.area',
            'planesAccion.riesgos' => fn ($q) => $q->visiblePara(Auth::user()),
            'planesAccion.riesgos.estado', 'planesAccion.riesgos.tipoRiesgo', 'planesAccion.riesgos.area',
            'user', 'area',
        ]);

        return view('auditoria.tarea.show', compact('tarea'));
    }

    public function edit(Tarea $tarea)
    {
        $this->authorize('update', $tarea);

        if ($tarea->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.tareas.show', $tarea)
                ->with('error', 'La tarea ya fue validada. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $areas = Area::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('auditoria.tarea.edit', compact('tarea', 'areas', 'usuarios'));
    }

    public function update(Request $request, Tarea $tarea)
    {
        $this->authorize('update', $tarea);

        if ($tarea->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.tareas.show', $tarea)
                ->with('error', 'La tarea ya fue validada. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'porcentaje_avance' => 'required|integer|min:0|max:100',
            'fecha' => 'nullable|date',
            'area_id' => 'nullable|exists:areas,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $original = $tarea->only(array_keys($data));
        $tarea->update($data);

        $diff = [];
        foreach ($data as $campo => $nuevo) {
            if (array_key_exists($campo, $original) && $original[$campo] != $nuevo) {
                $diff[$campo] = ['antes' => $original[$campo], 'despues' => $nuevo];
            }
        }
        if (! empty($diff)) {
            $tarea->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Borrador modificado',
                'estado_id' => Estado::borrador()->id,
                'data' => ['tipo' => 'edicion', 'diff' => ['campos' => $diff]],
            ]);
        }

        return redirect()->route('auditoria.tareas.show', $tarea)->with('ok', 'Tarea actualizada.');
    }

    public function destroy(Tarea $tarea)
    {
        $this->authorize('delete', $tarea);

        $tarea->delete();

        return redirect()->route('auditoria.tareas.index')->with('ok', 'Tarea eliminada.');
    }

    /**
     * Valida la tarea y arrastra a "validado" todas sus actualizaciones que
     * seguían en borrador, para que queden listas para aprobar() junto a la tarea.
     */
    public function validar(Tarea $tarea)
    {
        $this->authorize('validar', $tarea);

        $tarea->update(['estado_id' => Estado::validado()->id]);

        $tarea->actualizaciones()
            ->where('estado_id', Estado::borrador()->id)
            ->update(['estado_id' => Estado::validado()->id]);

        $tarea->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Validado por '.Auth::user()->name,
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'validacion'],
        ]);

        return back()->with('ok', 'Tarea validada correctamente.');
    }

    /**
     * Aprueba la tarea y aplica los cambios de todas sus actualizaciones ya
     * validadas (creación y ediciones acumuladas) en una sola transacción.
     */
    public function aprobar(Tarea $tarea)
    {
        $this->authorize('aprobar', $tarea);

        DB::transaction(function () use ($tarea) {
            $pendientes = $tarea->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->get();

            foreach ($pendientes as $act) {
                $this->aplicarCambiosActualizacion($act, $tarea);
                $act->update(['estado_id' => Estado::aprobado()->id]);
            }

            $tarea->update(['estado_id' => Estado::aprobado()->id]);
            $this->logAprobado($tarea, 'Aprobado por '.Auth::user()->name);
        });

        return back()->with('ok', 'Tarea aprobada correctamente.');
    }

    public function rechazar(Tarea $tarea)
    {
        $this->authorize('rechazar', $tarea);

        $tarea->update(['estado_id' => Estado::borrado()->id]);
        $this->logAprobado($tarea, 'Rechazado por '.Auth::user()->name);

        return back()->with('ok', 'Tarea rechazada.');
    }

    /**
     * Deja constancia en `actualizaciones` de una transición de estado (aprobar
     * o rechazar) con el mensaje dado; no crea una actualización pendiente de
     * aprobación, es sólo el registro de auditoría de la transición.
     */
    private function logAprobado($model, string $mensaje, array $campos = []): void
    {
        $model->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => $mensaje,
            'estado_id' => Estado::aprobado()->id,
            'data' => empty($campos) ? null : ['campos' => $campos],
        ]);
    }

    /**
     * Aplica sobre $model los cambios guardados en `data` de una Actualizacion ya
     * validada: actualiza `data['campos']` y sincroniza las relaciones many-to-many
     * de `data['relaciones']` (sync/attach/detach). No hace nada si `data` está
     * vacío o si el tipo no es 'cambio'. Duplica la lógica de
     * ActualizacionController::aplicarCambios() para este controlador.
     */
    private function aplicarCambiosActualizacion($actualizacion, $model): void
    {
        $data = $actualizacion->data ?? [];
        if (empty($data)) {
            return;
        }

        $tipo = $data['tipo'] ?? null;
        if ($tipo !== null && $tipo !== 'cambio') {
            return;
        }

        if (! empty($data['campos'])) {
            $model->update($data['campos']);
        }

        if (! empty($data['relaciones'])) {
            foreach ($data['relaciones'] as $relacion => $ops) {
                if (isset($ops['sync'])) {
                    $model->$relacion()->sync($ops['sync']);
                }
                if (isset($ops['attach'])) {
                    $model->$relacion()->attach($ops['attach']);
                }
                if (isset($ops['detach'])) {
                    $model->$relacion()->detach($ops['detach']);
                }
            }
        }
    }
}
