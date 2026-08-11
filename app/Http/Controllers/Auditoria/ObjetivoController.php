<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de Objetivo más su ciclo de vida de estados: borrador → validado → aprobado,
 * o borrador/validado → borrado (rechazo). Un objetivo validado ya no se edita acá;
 * los cambios posteriores pasan por el sistema de Actualizaciones.
 */
class ObjetivoController extends Controller
{
    public function index()
    {
        $objetivos = Objetivo::with(['riesgos', 'user', 'area'])
            ->visiblePara(Auth::user())
            ->latest()->paginate(20);

        return view('auditoria.objetivo.index', compact('objetivos'));
    }

    public function create()
    {
        $areas = Area::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('auditoria.objetivo.create', compact('areas', 'usuarios'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', [Objetivo::class, $request->input('area_id')]);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_objetivo' => 'nullable|date',
            'estrategico' => 'boolean',
            'anticorrupcion' => 'boolean',
            'area_id' => 'nullable|exists:areas,id',
            'user_id' => 'nullable|exists:users,id',
        ]);
        $data['estrategico'] = $request->boolean('estrategico');
        $data['anticorrupcion'] = $request->boolean('anticorrupcion');

        $data['user_id'] = $data['user_id'] ?? Auth::id();
        $objetivo = Objetivo::create($data);
        $objetivo->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Objetivo creado',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'creacion', 'campos' => [
                'nombre' => $objetivo->nombre,
                'descripcion' => $objetivo->descripcion,
                'fecha_objetivo' => $objetivo->fecha_objetivo?->format('Y-m-d'),
                'estrategico' => $objetivo->estrategico,
                'anticorrupcion' => $objetivo->anticorrupcion,
            ]],
        ]);

        return redirect()->route('auditoria.objetivos.index')->with('ok', 'Objetivo creado.');
    }

    public function show(Objetivo $objetivo)
    {
        $this->authorize('view', $objetivo);
        $user = Auth::user();
        // riesgos filtrados por visibilidad: un borrador de otra gerencia no debe
        // aparecer como fila en "Riesgos asociados" (axioma 3 + scopeVisiblePara).
        // "Planes de acción vinculados" en la vista se deriva de riesgos.planesAccion,
        // así que ese también necesita su propio filtro: un plan puede ser de una
        // gerencia distinta a la del riesgo que lo asocia. Los "borrado" quedan en
        // limbo (nunca se muestran, ver CLAUDE.md).
        $objetivo->load([
            'riesgos' => fn ($q) => $q->visiblePara($user),
            'riesgos.estado', 'riesgos.tipoRiesgo', 'riesgos.area',
            // riesgos.controles.estado: lo usa el accessor valor_residual (sólo mitigan
            // los controles aprobados); antes no se cargaba y generaba N+1.
            'riesgos.controles.estado',
            'riesgos.planesAccion' => fn ($q) => $q->visiblePara($user)->whereNot('estado_id', Estado::borrado()->id),
            'riesgos.planesAccion.estado', 'riesgos.planesAccion.area',
            'riesgos.planesAccion.tareas.estado', 'riesgos.planesAccion.tareas.area', 'riesgos.planesAccion.tareas.user',
            'user', 'area',
        ]);

        return view('auditoria.objetivo.show', compact('objetivo'));
    }

    public function edit(Objetivo $objetivo)
    {
        $this->authorize('update', $objetivo);

        if ($objetivo->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.objetivos.show', $objetivo)
                ->with('error', 'El objetivo ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $areas = Area::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('auditoria.objetivo.edit', compact('objetivo', 'areas', 'usuarios'));
    }

    public function update(Request $request, Objetivo $objetivo)
    {
        $this->authorize('update', $objetivo);

        if ($objetivo->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.objetivos.show', $objetivo)
                ->with('error', 'El objetivo ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_objetivo' => 'nullable|date',
            'estrategico' => 'boolean',
            'anticorrupcion' => 'boolean',
            'area_id' => 'nullable|exists:areas,id',
            'user_id' => 'nullable|exists:users,id',
        ]);
        $data['estrategico'] = $request->boolean('estrategico');
        $data['anticorrupcion'] = $request->boolean('anticorrupcion');

        $original = $objetivo->only(array_keys($data));
        $objetivo->update($data);

        $diff = [];
        foreach ($data as $campo => $nuevo) {
            if (array_key_exists($campo, $original) && $original[$campo] != $nuevo) {
                $diff[$campo] = ['antes' => $original[$campo], 'despues' => $nuevo];
            }
        }
        if (! empty($diff)) {
            $objetivo->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Borrador modificado',
                'estado_id' => Estado::borrador()->id,
                'data' => ['tipo' => 'edicion', 'diff' => ['campos' => $diff]],
            ]);
        }

        return redirect()->route('auditoria.objetivos.show', $objetivo)->with('ok', 'Objetivo actualizado.');
    }

    public function destroy(Objetivo $objetivo)
    {
        $this->authorize('delete', $objetivo);

        $objetivo->delete();

        return redirect()->route('auditoria.objetivos.index')->with('ok', 'Objetivo eliminado.');
    }

    /**
     * Valida el objetivo y arrastra a "validado" todas sus actualizaciones que
     * seguían en borrador, para que queden listas para aprobar() junto al objetivo.
     */
    public function validar(Objetivo $objetivo)
    {
        $this->authorize('validar', $objetivo);

        $objetivo->update(['estado_id' => Estado::validado()->id]);

        $objetivo->actualizaciones()
            ->where('estado_id', Estado::borrador()->id)
            ->update(['estado_id' => Estado::validado()->id]);

        $objetivo->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Validado por '.Auth::user()->name,
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'validacion'],
        ]);

        return back()->with('ok', 'Objetivo validado correctamente.');
    }

    /**
     * Aprueba el objetivo y aplica los cambios de todas sus actualizaciones ya
     * validadas (creación y ediciones acumuladas) en una sola transacción.
     */
    public function aprobar(Objetivo $objetivo)
    {
        $this->authorize('aprobar', $objetivo);

        DB::transaction(function () use ($objetivo) {
            $pendientes = $objetivo->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->get();

            foreach ($pendientes as $act) {
                $this->aplicarCambiosActualizacion($act, $objetivo);
                $act->update(['estado_id' => Estado::aprobado()->id]);
            }

            $objetivo->update(['estado_id' => Estado::aprobado()->id]);
            $this->logAprobado($objetivo, 'Aprobado por '.Auth::user()->name);
        });

        return back()->with('ok', 'Objetivo aprobado correctamente.');
    }

    public function rechazar(Objetivo $objetivo)
    {
        $this->authorize('rechazar', $objetivo);

        $objetivo->update(['estado_id' => Estado::borrado()->id]);
        $this->logAprobado($objetivo, 'Rechazado por '.Auth::user()->name);

        return back()->with('ok', 'Objetivo rechazado.');
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
