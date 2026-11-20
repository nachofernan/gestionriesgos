<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Auditoria\Concerns\OpcionesAreaResponsable;
use App\Http\Controllers\Auditoria\Concerns\ProponeEliminacion;
use App\Http\Controllers\Auditoria\Concerns\RechazaElemento;
use App\Http\Controllers\Controller;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Testeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CRUD de Control más su ciclo de vida de estados: borrador → validado → aprobado,
 * o borrador/validado → borrado (rechazo). Un control validado ya no se edita acá;
 * los cambios posteriores pasan por el sistema de Actualizaciones.
 */
class ControlController extends Controller
{
    use OpcionesAreaResponsable, ProponeEliminacion, RechazaElemento;

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
        $this->authorize('create', Control::class);

        return view('auditoria.control.create', $this->opcionesAreaResponsable());
    }

    public function store(Request $request)
    {
        $this->authorize('create', [Control::class, $request->input('area_id')]);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ] + $this->reglasAreaResponsable($request), self::MENSAJES_AREA_RESPONSABLE);

        $data['user_id'] = $data['user_id'] ?? Auth::id();
        $data['interno'] = $request->boolean('interno');
        $control = Control::create($data);
        $control->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Control creado',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'creacion', 'campos' => [
                'nombre' => $control->nombre,
                'descripcion' => $control->descripcion,
            ]],
        ]);

        return redirect()->route('auditoria.controles.index')->with('ok', 'Control creado.');
    }

    public function show(Control $control)
    {
        $this->authorize('view', $control);
        // Los riesgos asociados (con lo que necesita su valor_residual y filtrados
        // por visibilidad) los carga RiesgosControl, que se refresca solo.
        $control->load(['estado', 'area', 'user']);

        // Comité de la aprobación vigente (D-039), para el encabezado.
        $comite = Actualizacion::comiteVigenteDe($control);

        // Modal de eliminación por propuesta de lo aprobado (D-047, D-058).
        $eliminacion = $this->datosEliminacion($control);

        return view('auditoria.control.show', compact('control', 'comite', 'eliminacion'));
    }

    public function edit(Control $control)
    {
        $this->authorize('update', $control);

        if ($control->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.controles.show', $control)
                ->with('error', 'El control ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        return view('auditoria.control.edit', ['control' => $control]
            + $this->opcionesAreaResponsable($control->area_id, $control->user_id));
    }

    /** Edición de un control en borrador. El cuestionario se contesta aparte, en ValorControl. */
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
        ] + $this->reglasAreaResponsable($request, $control->area_id, $control->user_id), self::MENSAJES_AREA_RESPONSABLE);

        $data['interno'] = $request->boolean('interno');
        $campos = array_keys($data);
        $original = $control->only($campos);
        $control->update($data);

        $diff = [];
        foreach ($campos as $campo) {
            [$antes, $nuevo] = [$original[$campo], $control->$campo];
            if ($antes != $nuevo) {
                $diff[$campo] = ['antes' => $antes, 'despues' => $nuevo];
            }
        }
        if (! empty($diff)) {
            $control->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Borrador modificado',
                'estado_id' => Estado::borrador()->id,
                'data' => ['tipo' => 'edicion', 'diff' => ['campos' => $diff]],
            ]);
        }

        return redirect()->route('auditoria.controles.show', $control)->with('ok', 'Control actualizado.');
    }

    /** Adjunto de un testeo (D-040): lo descarga quien puede ver el control. */
    public function descargarAdjuntoTesteo(Testeo $testeo, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $testeo->control);

        abort_unless($media->model_type === Testeo::class && $media->model_id === $testeo->id, 404);

        return response()->download($media->getPath(), $media->file_name);
    }

    /**
     * Propuesta de eliminación de lo aprobado (D-047, D-058), con motivo
     * obligatorio. La aplica el comité al aprobarla.
     * Tests: EliminacionPorPropuestaTest.
     */
    public function proponerEliminacion(Request $request, Control $control)
    {
        $this->authorize('proponerEliminacion', $control);

        return $this->registrarPropuestaEliminacion($request, $control, 'controles', 'Control eliminado.');
    }

    /** Borrado directo de lo que todavía no está aprobado (ver la Policy). */
    public function destroy(Control $control)
    {
        $this->authorize('delete', $control);

        $control->delete();

        return redirect()->route('auditoria.controles.index')->with('ok', 'Control eliminado.');
    }

    /**
     * Valida el control y arrastra a "validado" todas sus actualizaciones que
     * seguían en borrador, para que queden listas para aprobar() junto al control.
     * Sin el cuestionario contestado no se valida (Control::motivosBloqueoValidacion(), D-051).
     * Test: un_control_sin_cuestionario_no_se_valida.
     */
    public function validar(Control $control)
    {
        $this->authorize('validar', $control);

        if ($motivos = $control->motivosBloqueoValidacion()) {
            return back()->with('error', implode(' ', $motivos));
        }

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
     * Actualizacion::aplicarCambios(). Sin cuestionario no se aprueba (D-051).
     * Test: un_control_sin_cuestionario_no_se_valida.
     */
    public function aprobar(Request $request, Control $control)
    {
        $this->authorize('aprobar', $control);

        if ($motivos = $control->motivosBloqueoValidacion()) {
            return back()->with('error', implode(' ', $motivos));
        }
        $comite = $request->validate(Actualizacion::reglasComite())['comite'] ?? null;

        DB::transaction(function () use ($control, $comite) {
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
            $this->logAprobado($control, 'Aprobado por '.Auth::user()->name, comite: $comite);
        });

        return back()->with('ok', 'Control aprobado correctamente.');
    }

    /** Rechazo con motivo (D-031). */
    public function rechazar(Request $request, Control $control)
    {
        $this->authorize('rechazar', $control);
        $motivo = $this->motivoRechazo($request);

        $control->update(['estado_id' => Estado::borrado()->id]);
        Actualizacion::registrarRechazo($control, Auth::user(), $motivo);

        return back()->with('ok', 'Control rechazado.');
    }

    /**
     * Deja constancia en `actualizaciones` de una transición de estado (aprobar
     * o rechazar) con el mensaje dado; no crea una actualización pendiente de
     * aprobación, es sólo el registro de auditoría de la transición.
     */
    private function logAprobado($model, string $mensaje, array $campos = [], ?array $comite = null): void
    {
        $model->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => $mensaje,
            'estado_id' => Estado::aprobado()->id,
            'data' => empty($campos) ? null : ['campos' => $campos],
        ] + Actualizacion::columnasComite($comite));
    }
}
