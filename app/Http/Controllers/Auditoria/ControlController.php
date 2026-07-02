<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de Control más su ciclo de vida de estados: borrador → validado → activo,
 * o borrador/validado → borrado (rechazo). Un control validado ya no se edita acá;
 * los cambios posteriores pasan por el sistema de Actualizaciones.
 */
class ControlController extends Controller
{
    public function index()
    {
        $controles = Control::with(['riesgos', 'user', 'area'])
            ->visiblePara(Auth::user())
            ->latest()->paginate(20);

        return view('auditoria.control.index', compact('controles'));
    }

    public function create()
    {
        $areas    = Area::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('auditoria.control.create', compact('areas', 'usuarios'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', [Control::class, $request->input('area_id')]);

        $data = $request->validate([
            'nombre'             => 'required|string|max:255',
            'descripcion'        => 'nullable|string',
            'mitigacion_default' => 'required|integer|min:1|max:10',
            'area_id'            => 'nullable|exists:areas,id',
            'user_id'            => 'nullable|exists:users,id',
        ]);

        $data['user_id'] = $data['user_id'] ?? Auth::id();
        $control = Control::create($data);
        $control->actualizaciones()->create([
            'user_id'   => Auth::id(),
            'mensaje'   => 'Control creado',
            'estado_id' => Estado::borrador()->id,
            'data'      => ['tipo' => 'creacion', 'campos' => [
                'nombre'             => $control->nombre,
                'descripcion'        => $control->descripcion,
                'mitigacion_default' => $control->mitigacion_default,
            ]],
        ]);

        return redirect()->route('auditoria.controles.index')->with('ok', 'Control creado.');
    }

    public function show(Control $control)
    {
        $this->authorize('view', $control);
        $control->load(['riesgos.controles', 'riesgos.estado', 'riesgos.tipoRiesgo', 'riesgos.area', 'user', 'area']);

        return view('auditoria.control.show', compact('control'));
    }

    public function edit(Control $control)
    {
        $this->authorize('update', $control);

        if ($control->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.controles.show', $control)
                ->with('error', 'El control ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $areas    = Area::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('auditoria.control.edit', compact('control', 'areas', 'usuarios'));
    }

    public function update(Request $request, Control $control)
    {
        $this->authorize('update', $control);

        if ($control->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.controles.show', $control)
                ->with('error', 'El control ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $data = $request->validate([
            'nombre'             => 'required|string|max:255',
            'descripcion'        => 'nullable|string',
            'mitigacion_default' => 'required|integer|min:1|max:10',
            'area_id'            => 'nullable|exists:areas,id',
            'user_id'            => 'nullable|exists:users,id',
        ]);

        $original = $control->only(array_keys($data));
        $control->update($data);

        $diff = [];
        foreach ($data as $campo => $nuevo) {
            if (array_key_exists($campo, $original) && $original[$campo] != $nuevo) {
                $diff[$campo] = ['antes' => $original[$campo], 'despues' => $nuevo];
            }
        }
        if (!empty($diff)) {
            $control->actualizaciones()->create([
                'user_id'   => Auth::id(),
                'mensaje'   => 'Borrador modificado',
                'estado_id' => Estado::borrador()->id,
                'data'      => ['tipo' => 'edicion', 'diff' => ['campos' => $diff]],
            ]);
        }

        return redirect()->route('auditoria.controles.show', $control)->with('ok', 'Control actualizado.');
    }

    public function destroy(Control $control)
    {
        $this->authorize('delete', $control);

        $control->delete();

        return redirect()->route('auditoria.controles.index')->with('ok', 'Control eliminado.');
    }

    /**
     * Valida el control y arrastra a "validado" todas sus actualizaciones que
     * seguían en borrador, para que queden listas para activar() junto al control.
     */
    public function validar(Control $control)
    {
        $this->authorize('validar', $control);

        $control->update(['estado_id' => Estado::validado()->id]);

        $control->actualizaciones()
            ->where('estado_id', Estado::borrador()->id)
            ->update(['estado_id' => Estado::validado()->id]);

        $control->actualizaciones()->create([
            'user_id'   => Auth::id(),
            'mensaje'   => 'Validado por ' . Auth::user()->name,
            'estado_id' => Estado::validado()->id,
            'data'      => ['tipo' => 'validacion'],
        ]);

        return back()->with('ok', 'Control validado correctamente.');
    }

    /**
     * Activa el control y aplica los cambios de todas sus actualizaciones ya
     * validadas (creación y ediciones acumuladas) en una sola transacción.
     */
    public function activar(Control $control)
    {
        $this->authorize('activar', $control);

        DB::transaction(function () use ($control) {
            $pendientes = $control->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->get();

            foreach ($pendientes as $act) {
                $this->aplicarCambiosActualizacion($act, $control);
                $act->update(['estado_id' => Estado::activo()->id]);
            }

            $control->update(['estado_id' => Estado::activo()->id]);
            $this->logActivo($control, 'Activado por ' . Auth::user()->name);
        });

        return back()->with('ok', 'Control activado correctamente.');
    }

    public function rechazar(Control $control)
    {
        $this->authorize('rechazar', $control);

        $control->update(['estado_id' => Estado::borrado()->id]);
        $this->logActivo($control, 'Rechazado por ' . Auth::user()->name);

        return back()->with('ok', 'Control rechazado.');
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
}
