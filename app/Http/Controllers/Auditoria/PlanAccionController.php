<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRUD de PlanAccion más su ciclo de vida de estados: borrador → validado → activo,
 * o borrador/validado → borrado (rechazo). Un plan validado ya no se edita acá;
 * los cambios posteriores pasan por el sistema de Actualizaciones.
 */
class PlanAccionController extends Controller
{
    public function index()
    {
        $planAccions = PlanAccion::with(['riesgos', 'tareas', 'user', 'area'])
            ->visiblePara(Auth::user())
            ->latest()->paginate(20);

        return view('auditoria.planaccion.index', compact('planAccions'));
    }

    public function create()
    {
        $riesgos        = Riesgo::visiblePara(Auth::user())->orderBy('nombre')->get();
        $areas          = Area::orderBy('nombre')->get();
        $usuarios       = User::orderBy('name')->get();
        $codigoSugerido = $this->generarCodigo();

        return view('auditoria.planaccion.create', compact('riesgos', 'areas', 'usuarios', 'codigoSugerido'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', [PlanAccion::class, $request->input('area_id')]);

        $data = $request->validate([
            'codigo'       => 'required|string|max:100|unique:planes_accion,codigo',
            'nombre'       => 'required|string|max:255',
            'descripcion'  => 'nullable|string',
            'riesgo_ids'   => 'nullable|array',
            'riesgo_ids.*' => ['exists:riesgos,id', Rule::in(Riesgo::visiblePara(Auth::user())->pluck('id')->toArray())],
            'area_id'      => 'nullable|exists:areas,id',
            'user_id'      => 'nullable|exists:users,id',
        ]);

        $riesgoIds = $data['riesgo_ids'] ?? [];
        unset($data['riesgo_ids']);
        $data['user_id'] = $data['user_id'] ?? Auth::id();

        $plan = PlanAccion::create($data);
        if (!empty($riesgoIds)) {
            $plan->riesgos()->sync($riesgoIds);
        }
        $plan->actualizaciones()->create([
            'user_id'   => Auth::id(),
            'mensaje'   => 'Plan de acción creado',
            'estado_id' => Estado::borrador()->id,
            'data'      => ['tipo' => 'creacion', 'campos' => [
                'codigo'      => $plan->codigo,
                'nombre'      => $plan->nombre,
                'descripcion' => $plan->descripcion,
            ]],
        ]);

        return redirect()->route('auditoria.planes.index')->with('ok', 'Plan de acción creado.');
    }

    public function show(PlanAccion $planAccion)
    {
        $this->authorize('view', $planAccion);
        $planAccion->load(['riesgos.estado', 'riesgos.tipoRiesgo', 'riesgos.area', 'tareas', 'user', 'area']);

        return view('auditoria.planaccion.show', compact('planAccion'));
    }

    public function edit(PlanAccion $planAccion)
    {
        $this->authorize('update', $planAccion);

        if ($planAccion->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.planes.show', $planAccion)
                ->with('error', 'El plan ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $planAccion->load('riesgos');
        $riesgos  = Riesgo::visiblePara(Auth::user())->orderBy('nombre')->get();
        $areas    = Area::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('auditoria.planaccion.edit', compact('planAccion', 'riesgos', 'areas', 'usuarios'));
    }

    public function update(Request $request, PlanAccion $planAccion)
    {
        $this->authorize('update', $planAccion);

        if ($planAccion->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.planes.show', $planAccion)
                ->with('error', 'El plan ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $data = $request->validate([
            'codigo'       => 'required|string|max:100|unique:planes_accion,codigo,' . $planAccion->id,
            'nombre'       => 'required|string|max:255',
            'descripcion'  => 'nullable|string',
            'riesgo_ids'   => 'nullable|array',
            'riesgo_ids.*' => ['exists:riesgos,id', Rule::in(Riesgo::visiblePara(Auth::user())->pluck('id')->toArray())],
            'area_id'      => 'nullable|exists:areas,id',
            'user_id'      => 'nullable|exists:users,id',
        ]);

        $riesgoIds = $data['riesgo_ids'] ?? [];
        unset($data['riesgo_ids']);

        $original = $planAccion->only(array_keys($data));
        $planAccion->update($data);
        $planAccion->riesgos()->sync($riesgoIds);

        $diff = [];
        foreach ($data as $campo => $nuevo) {
            if (array_key_exists($campo, $original) && $original[$campo] != $nuevo) {
                $diff[$campo] = ['antes' => $original[$campo], 'despues' => $nuevo];
            }
        }
        if (!empty($diff)) {
            $planAccion->actualizaciones()->create([
                'user_id'   => Auth::id(),
                'mensaje'   => 'Borrador modificado',
                'estado_id' => Estado::borrador()->id,
                'data'      => ['tipo' => 'edicion', 'diff' => ['campos' => $diff]],
            ]);
        }

        return redirect()->route('auditoria.planes.show', $planAccion)->with('ok', 'Plan actualizado.');
    }

    public function destroy(PlanAccion $planAccion)
    {
        $this->authorize('delete', $planAccion);

        $planAccion->delete();

        return redirect()->route('auditoria.planes.index')->with('ok', 'Plan de acción eliminado.');
    }

    /**
     * Reemplaza el conjunto de tareas asociadas al plan por el enviado (sync
     * completo, no incremental).
     */
    public function asociarTareas(Request $request, PlanAccion $planAccion)
    {
        $this->authorize('update', $planAccion);

        $request->validate([
            'tareas'   => 'nullable|array',
            'tareas.*' => 'exists:tareas,id',
        ]);

        $planAccion->tareas()->sync($request->input('tareas', []));

        return redirect()->route('auditoria.planes.show', $planAccion)->with('ok', 'Tareas actualizadas.');
    }

    /**
     * Valida el plan y arrastra a "validado" todas sus actualizaciones que
     * seguían en borrador, para que queden listas para activar() junto al plan.
     */
    public function validar(PlanAccion $planAccion)
    {
        $this->authorize('validar', $planAccion);

        $planAccion->update(['estado_id' => Estado::validado()->id]);

        $planAccion->actualizaciones()
            ->where('estado_id', Estado::borrador()->id)
            ->update(['estado_id' => Estado::validado()->id]);

        $planAccion->actualizaciones()->create([
            'user_id'   => Auth::id(),
            'mensaje'   => 'Validado por ' . Auth::user()->name,
            'estado_id' => Estado::validado()->id,
            'data'      => ['tipo' => 'validacion'],
        ]);

        return back()->with('ok', 'Plan validado correctamente.');
    }

    /**
     * Activa el plan y aplica los cambios de todas sus actualizaciones ya
     * validadas (creación y ediciones acumuladas) en una sola transacción.
     */
    public function activar(PlanAccion $planAccion)
    {
        $this->authorize('activar', $planAccion);

        DB::transaction(function () use ($planAccion) {
            $pendientes = $planAccion->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->get();

            foreach ($pendientes as $act) {
                $this->aplicarCambiosActualizacion($act, $planAccion);
                $act->update(['estado_id' => Estado::activo()->id]);
            }

            $planAccion->update(['estado_id' => Estado::activo()->id]);
            $this->logActivo($planAccion, 'Activado por ' . Auth::user()->name);
        });

        return back()->with('ok', 'Plan activado correctamente.');
    }

    public function rechazar(PlanAccion $planAccion)
    {
        $this->authorize('rechazar', $planAccion);

        $planAccion->update(['estado_id' => Estado::borrado()->id]);
        $this->logActivo($planAccion, 'Rechazado por ' . Auth::user()->name);

        return back()->with('ok', 'Plan rechazado.');
    }

    /**
     * Deja constancia en `actualizaciones` de una transición de estado (activar
     * o rechazar) con el mensaje dado; no crea una actualización pendiente de
     * aprobación, es sólo el registro de auditoría de la transición.
     */
    private function logActivo($model, string $mensaje, array $campos = []): void
    {
        $model->actualizaciones()->create([
            'user_id'   => Auth::id(),
            'mensaje'   => $mensaje,
            'estado_id' => Estado::activo()->id,
            'data'      => empty($campos) ? null : ['campos' => $campos],
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
        if (empty($data)) return;

        $tipo = $data['tipo'] ?? null;
        if ($tipo !== null && $tipo !== 'cambio') return;

        if (!empty($data['campos'])) {
            $model->update($data['campos']);
        }

        if (!empty($data['relaciones'])) {
            foreach ($data['relaciones'] as $relacion => $ops) {
                if (isset($ops['sync']))   $model->$relacion()->sync($ops['sync']);
                if (isset($ops['attach'])) $model->$relacion()->attach($ops['attach']);
                if (isset($ops['detach'])) $model->$relacion()->detach($ops['detach']);
            }
        }
    }

    /**
     * Genera el próximo código correlativo (PA-0001, PA-0002, ...) a partir del
     * último plan creado, incluyendo los borrados lógicamente (withTrashed) para
     * no reutilizar códigos ya asignados.
     */
    private function generarCodigo(): string
    {
        $ultimo = PlanAccion::withTrashed()->orderByDesc('id')->first();
        $numero = $ultimo ? (intval(preg_replace('/\D/', '', $ultimo->codigo)) + 1) : 1;

        return 'PA-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
}
