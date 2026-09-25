<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CRUD de Control más su ciclo de vida de estados: borrador → validado → aprobado,
 * o borrador/validado → borrado (rechazo). Un control validado ya no se edita acá;
 * los cambios posteriores pasan por el sistema de Actualizaciones.
 */
class ControlController extends Controller
{
    public function index()
    {
        $controles = Control::with(['riesgos', 'user', 'area'])
            ->visiblePara(Auth::user())
            ->join('estados', 'estados.id', '=', 'controles.estado_id')
            ->select('controles.*')
            ->orderByRaw(Estado::ordenSql())
            ->orderByDesc('controles.created_at')
            ->paginate(20);

        return view('auditoria.control.index', compact('controles'));
    }

    public function create()
    {
        $areas = Area::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('auditoria.control.create', compact('areas', 'usuarios'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', [Control::class, $request->input('area_id')]);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'mitigacion_default' => 'required|integer|min:1|max:10',
            'area_id' => 'nullable|exists:areas,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $data['user_id'] = $data['user_id'] ?? Auth::id();
        $control = Control::create($data);
        $control->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Control creado',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'creacion', 'campos' => [
                'nombre' => $control->nombre,
                'descripcion' => $control->descripcion,
                'mitigacion_default' => $control->mitigacion_default,
            ]],
        ]);

        return redirect()->route('auditoria.controles.index')->with('ok', 'Control creado.');
    }

    public function show(Control $control)
    {
        $this->authorize('view', $control);
        // riesgos.controles.estado, riesgos.planesAccion.estado y
        // riesgos.planesAccion.tareas.estado: los usa el accessor valor_residual de
        // cada riesgo listado en la vista del control.
        // riesgos filtrados por visibilidad: un borrador de otra gerencia no debe
        // aparecer como fila en "Riesgos asociados" (axioma 3 + scopeVisiblePara).
        $control->load([
            'riesgos' => fn ($q) => $q->visiblePara(Auth::user()),
            'riesgos.controles.estado', 'riesgos.planesAccion.estado', 'riesgos.planesAccion.tareas.estado',
            'riesgos.estado', 'riesgos.tipoRiesgo', 'riesgos.area', 'user', 'area',
        ]);
        $control->setRelation('riesgos', Estado::ordenarColeccion($control->riesgos));

        return view('auditoria.control.show', compact('control'));
    }

    public function edit(Control $control)
    {
        $this->authorize('update', $control);

        if ($control->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.controles.show', $control)
                ->with('error', 'El control ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $areas = Area::orderBy('nombre')->get();
        $usuarios = User::orderBy('name')->get();

        return view('auditoria.control.edit', compact('control', 'areas', 'usuarios'));
    }

    /**
     * Edición de un control en borrador. Con `propagar_mitigacion` tildado y el
     * default cambiado, lleva el nuevo valor a todos los riesgos asociados
     * (Control::propagarMitigacionDefault()).
     * Test: editar_un_borrador_con_propagar_pisa_las_asociaciones.
     */
    public function update(Request $request, Control $control)
    {
        $this->authorize('update', $control);

        if ($control->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.controles.show', $control)
                ->with('error', 'El control ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'mitigacion_default' => 'required|integer|min:1|max:10',
            'area_id' => 'nullable|exists:areas,id',
            'user_id' => 'nullable|exists:users,id',
        ]);
        $propagar = $request->boolean('propagar_mitigacion');

        $original = $control->only(array_keys($data));
        DB::transaction(function () use ($control, $data, $original, $propagar) {
            $control->update($data);
            if ($propagar && $original['mitigacion_default'] != $data['mitigacion_default']) {
                $control->propagarMitigacionDefault((int) $original['mitigacion_default'], Auth::user());
            }
        });

        $diff = [];
        foreach ($data as $campo => $nuevo) {
            if (array_key_exists($campo, $original) && $original[$campo] != $nuevo) {
                $diff[$campo] = ['antes' => $original[$campo], 'despues' => $nuevo];
            }
        }
        if (! empty($diff)) {
            $control->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Borrador modificado',
                'estado_id' => Estado::borrador()->id,
                'data' => array_filter([
                    'tipo' => 'edicion',
                    'diff' => ['campos' => $diff],
                    'propagar_mitigacion' => $propagar && isset($diff['mitigacion_default']) ?: null,
                ]),
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
     * seguían en borrador, para que queden listas para aprobar() junto al control.
     */
    public function validar(Control $control)
    {
        $this->authorize('validar', $control);

        $control->update(['estado_id' => Estado::validado()->id]);

        $control->actualizaciones()
            ->where('estado_id', Estado::borrador()->id)
            ->update(['estado_id' => Estado::validado()->id]);

        $control->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Validado por '.Auth::user()->name,
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'validacion'],
        ]);

        return back()->with('ok', 'Control validado correctamente.');
    }

    /**
     * Aprueba el control y aplica los cambios de todas sus actualizaciones ya
     * validadas (creación y ediciones acumuladas) en una sola transacción, vía
     * Actualizacion::aplicarCambios() (que también propaga la mitigación default
     * si la propuesta lo pidió).
     * Test: aprobar_el_control_aplica_la_propagacion_de_sus_actualizaciones_pendientes.
     */
    public function aprobar(Control $control)
    {
        $this->authorize('aprobar', $control);

        DB::transaction(function () use ($control) {
            // reorder('id') limpia el latest() de la relación y ordena por id en vez
            // de created_at: la columna es timestamp (precisión de 1 segundo) y dos
            // actualizaciones seguidas pueden empatar, así que created_at solo no
            // desempata de forma confiable. id es autoincremental y sí lo es. Acá
            // importa que la más reciente quede aplicada al final (gana).
            $pendientes = $control->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->reorder('id')
                ->get();

            foreach ($pendientes as $act) {
                $act->aplicarCambios(Auth::user());
                $act->update(['estado_id' => Estado::aprobado()->id]);
            }

            $control->update(['estado_id' => Estado::aprobado()->id]);
            $this->logAprobado($control, 'Aprobado por '.Auth::user()->name);
        });

        return back()->with('ok', 'Control aprobado correctamente.');
    }

    public function rechazar(Control $control)
    {
        $this->authorize('rechazar', $control);

        $control->update(['estado_id' => Estado::borrado()->id]);
        $this->logAprobado($control, 'Rechazado por '.Auth::user()->name);

        return back()->with('ok', 'Control rechazado.');
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
}
