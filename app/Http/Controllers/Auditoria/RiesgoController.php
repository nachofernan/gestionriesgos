<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRUD de Riesgo más su ciclo de vida de estados: borrador → validado → activo,
 * o borrador/validado → borrado (rechazo). Un riesgo validado ya no se edita acá;
 * los cambios posteriores pasan por el sistema de Actualizaciones.
 */
class RiesgoController extends Controller
{
    public function index()
    {
        $riesgos = Riesgo::with(['tipoRiesgo', 'estado', 'objetivos', 'user', 'area'])
            ->visiblePara(Auth::user())
            ->latest()->paginate(20);

        return view('auditoria.riesgo.index', compact('riesgos'));
    }

    public function create()
    {
        $tiposRiesgo = TipoRiesgo::all();
        $areas       = Area::orderBy('nombre')->get();
        $usuarios    = User::orderBy('name')->get();
        $objetivos   = Objetivo::visiblePara(Auth::user())->orderBy('nombre')->get();

        return view('auditoria.riesgo.create', compact('tiposRiesgo', 'areas', 'usuarios', 'objetivos'));
    }

    /**
     * `mayor_criticidad` sólo puede quedar en true si impacto + probabilidad >= 14;
     * el checkbox del request es una propuesta, la suma es la que decide.
     */
    public function store(Request $request)
    {
        $this->authorize('create', [Riesgo::class, $request->input('area_id')]);

        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'descripcion'     => 'nullable|string',
            'impacto'         => 'required|integer|min:0|max:10',
            'probabilidad'    => 'required|integer|min:0|max:10',
            'mayor_criticidad' => 'boolean',
            'tipo_riesgo_id'   => 'required|exists:tipos_riesgo,id',
            'area_id'          => 'nullable|exists:areas,id',
            'user_id'          => 'nullable|exists:users,id',
            'objetivos'        => 'required|array|min:1',
            'objetivos.*'      => ['exists:objetivos,id', Rule::in(Objetivo::visiblePara(Auth::user())->pluck('id')->toArray())],
        ]);

        $suma = ($data['impacto'] ?? 0) + ($data['probabilidad'] ?? 0);
        $data['mayor_criticidad'] = $suma >= 14 && $request->boolean('mayor_criticidad');
        $data['user_id']         = $data['user_id'] ?? Auth::id();
        $riesgo = Riesgo::create($data);
        $riesgo->objetivos()->sync($request->input('objetivos'));
        $riesgo->actualizaciones()->create([
            'user_id'   => Auth::id(),
            'mensaje'   => 'Riesgo creado',
            'estado_id' => Estado::borrador()->id,
            'data'      => ['tipo' => 'creacion', 'campos' => [
                'nombre'          => $riesgo->nombre,
                'descripcion'     => $riesgo->descripcion,
                'impacto'         => $riesgo->impacto,
                'probabilidad'    => $riesgo->probabilidad,
                'mayor_criticidad' => $riesgo->mayor_criticidad,
                'tipo_riesgo_id'  => $riesgo->tipo_riesgo_id,
            ]],
        ]);

        return redirect()->route('auditoria.riesgos.show', $riesgo)->with('ok', 'Riesgo creado correctamente.');
    }

    public function show(Riesgo $riesgo)
    {
        $this->authorize('view', $riesgo);
        $riesgo->load(['tipoRiesgo', 'estado', 'user', 'area']);

        return view('auditoria.riesgo.show', compact('riesgo'));
    }

    public function edit(Riesgo $riesgo)
    {
        $this->authorize('update', $riesgo);

        if ($riesgo->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.riesgos.show', $riesgo)
                ->with('error', 'El riesgo ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $tiposRiesgo = TipoRiesgo::orderBy('nombre')->get();
        $areas       = Area::orderBy('nombre')->get();
        $usuarios    = User::orderBy('name')->get();

        return view('auditoria.riesgo.edit', compact('riesgo', 'tiposRiesgo', 'areas', 'usuarios'));
    }

    public function update(Request $request, Riesgo $riesgo)
    {
        $this->authorize('update', $riesgo);

        if ($riesgo->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.riesgos.show', $riesgo)
                ->with('error', 'El riesgo ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'descripcion'     => 'nullable|string',
            'impacto'         => 'required|integer|min:0|max:10',
            'probabilidad'    => 'required|integer|min:0|max:10',
            'mayor_criticidad' => 'boolean',
            'tipo_riesgo_id'   => 'required|exists:tipos_riesgo,id',
            'area_id'          => 'nullable|exists:areas,id',
            'user_id'          => 'nullable|exists:users,id',
        ]);

        $suma = ($data['impacto'] ?? 0) + ($data['probabilidad'] ?? 0);
        $data['mayor_criticidad'] = $suma >= 14 && $request->boolean('mayor_criticidad');

        $original = $riesgo->only(array_keys($data));
        $riesgo->update($data);

        $diff = [];
        foreach ($data as $campo => $nuevo) {
            if (array_key_exists($campo, $original) && $original[$campo] != $nuevo) {
                $diff[$campo] = ['antes' => $original[$campo], 'despues' => $nuevo];
            }
        }
        if (!empty($diff)) {
            $riesgo->actualizaciones()->create([
                'user_id'   => Auth::id(),
                'mensaje'   => 'Borrador modificado',
                'estado_id' => Estado::borrador()->id,
                'data'      => ['tipo' => 'edicion', 'diff' => ['campos' => $diff]],
            ]);
        }

        return redirect()->route('auditoria.riesgos.show', $riesgo)->with('ok', 'Riesgo actualizado.');
    }

    public function destroy(Riesgo $riesgo)
    {
        $this->authorize('delete', $riesgo);

        $riesgo->delete();

        return redirect()->route('auditoria.riesgos.index')->with('ok', 'Riesgo eliminado.');
    }

    /**
     * Valida el riesgo y arrastra a "validado" todas sus actualizaciones que
     * seguían en borrador, para que queden listas para activar() junto al riesgo.
     */
    public function validar(Riesgo $riesgo)
    {
        $this->authorize('validar', $riesgo);

        $riesgo->update(['estado_id' => Estado::validado()->id]);

        $riesgo->actualizaciones()
            ->where('estado_id', Estado::borrador()->id)
            ->update(['estado_id' => Estado::validado()->id]);

        $riesgo->actualizaciones()->create([
            'user_id'   => Auth::id(),
            'mensaje'   => 'Validado por ' . Auth::user()->name,
            'estado_id' => Estado::validado()->id,
            'data'      => ['tipo' => 'validacion'],
        ]);

        return back()->with('ok', 'Riesgo validado correctamente.');
    }

    /**
     * Activa el riesgo y aplica los cambios de todas sus actualizaciones ya
     * validadas (creación y ediciones acumuladas) en una sola transacción.
     */
    public function activar(Riesgo $riesgo)
    {
        $this->authorize('activar', $riesgo);

        DB::transaction(function () use ($riesgo) {
            $pendientes = $riesgo->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->get();

            foreach ($pendientes as $act) {
                $this->aplicarCambiosActualizacion($act, $riesgo);
                $act->update(['estado_id' => Estado::activo()->id]);
            }

            $riesgo->update(['estado_id' => Estado::activo()->id]);
            $this->logActivo($riesgo, 'Activado por ' . Auth::user()->name);
        });

        return back()->with('ok', 'Riesgo activado correctamente.');
    }

    public function rechazar(Riesgo $riesgo)
    {
        $this->authorize('rechazar', $riesgo);

        $riesgo->update(['estado_id' => Estado::borrado()->id]);
        $this->logActivo($riesgo, 'Rechazado por ' . Auth::user()->name);

        return back()->with('ok', 'Riesgo rechazado.');
    }

    /**
     * Reemplaza el conjunto de controles asociados al riesgo por el enviado
     * (sync completo, no incremental).
     */
    public function asociarControles(Request $request, Riesgo $riesgo)
    {
        $this->authorize('update', $riesgo);

        $request->validate([
            'controles'   => 'nullable|array',
            'controles.*' => 'exists:controles,id',
        ]);

        $riesgo->controles()->sync($request->input('controles', []));

        return redirect()->route('auditoria.riesgos.show', $riesgo)->with('ok', 'Controles actualizados.');
    }

    /**
     * Reemplaza el conjunto de objetivos asociados al riesgo por el enviado
     * (sync completo, no incremental), restringido a objetivos visibles para el usuario.
     */
    public function asociarObjetivos(Request $request, Riesgo $riesgo)
    {
        $this->authorize('update', $riesgo);

        $request->validate([
            'objetivos'   => 'nullable|array',
            'objetivos.*' => ['exists:objetivos,id', Rule::in(Objetivo::visiblePara(Auth::user())->pluck('id')->toArray())],
        ]);

        $riesgo->objetivos()->sync($request->input('objetivos', []));

        return redirect()->route('auditoria.riesgos.show', $riesgo)->with('ok', 'Objetivos actualizados.');
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
