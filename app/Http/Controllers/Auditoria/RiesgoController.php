<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRUD de Riesgo más su ciclo de vida de estados: borrador → validado → aprobado,
 * o borrador/validado → borrado (rechazo). Un riesgo validado ya no se edita acá;
 * los cambios posteriores pasan por el sistema de Actualizaciones.
 */
class RiesgoController extends Controller
{
    private const MENSAJES_RESPUESTA = [
        'respuesta.required' => 'Debe elegir una respuesta frente al riesgo.',
        'respuesta.not_in' => 'Un riesgo de este tipo no puede compartirse ni aceptarse como respuesta.',
        'fundamento.required_if' => 'Debe fundamentar por qué se eligió esta respuesta frente al riesgo.',
    ];

    // reglaRespuesta()/reglaFundamento() viven en Riesgo (ver ahí): las consume
    // también GestionActualizaciones::guardar() para no duplicar la regla.

    public function index()
    {
        $riesgos = Riesgo::with(['tipoRiesgo', 'estado', 'objetivos', 'user', 'area'])
            ->visiblePara(Auth::user())
            ->join('estados', 'estados.id', '=', 'riesgos.estado_id')
            ->select('riesgos.*')
            ->orderByRaw(Estado::ordenSql())
            ->orderByDesc('riesgos.created_at')
            ->paginate(20);

        return view('auditoria.riesgo.index', compact('riesgos'));
    }

    public function create()
    {
        $tiposRiesgo = TipoRiesgo::all();
        $areas = Area::whereIn('id', Auth::user()->idsAreasGestionables())->orderBy('nombre')->get();
        $objetivos = Objetivo::visiblePara(Auth::user())->orderBy('nombre')->get();
        $preguntas = config('riesgo_preguntas');

        return view('auditoria.riesgo.create', compact('tiposRiesgo', 'areas', 'objetivos', 'preguntas'));
    }

    /**
     * Impacto y probabilidad no se cargan a mano: se calculan como la suma de
     * las 5 respuestas (0-2 cada una) del wizard de creación para cada dimensión
     * (ver config/riesgo_preguntas.php). `mayor_criticidad` sólo puede quedar en
     * true si esa suma total es >= 14; el checkbox del request es una propuesta,
     * la suma es la que decide. Objetivos es opcional acá: se vuelve obligatorio
     * recién al validar el riesgo (ver Riesgo::motivosBloqueoValidacion()).
     */
    public function store(Request $request)
    {
        $this->authorize('create', [Riesgo::class, $request->input('area_id')]);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'probabilidad_respuestas' => 'required|array|size:5',
            'probabilidad_respuestas.*' => 'required|integer|min:0|max:2',
            'impacto_respuestas' => 'required|array|size:5',
            'impacto_respuestas.*' => 'required|integer|min:0|max:2',
            'mayor_criticidad' => 'boolean',
            'respuesta' => Riesgo::reglaRespuesta($request->input('tipo_riesgo_id')),
            'fundamento' => Riesgo::reglaFundamento(),
            'tipo_riesgo_id' => 'required|exists:tipos_riesgo,id',
            'area_id' => 'nullable|exists:areas,id',
            'objetivos' => 'nullable|array',
            'objetivos.*' => ['exists:objetivos,id', Rule::in(Objetivo::visiblePara(Auth::user())->pluck('id')->toArray())],
        ], self::MENSAJES_RESPUESTA);

        $probabilidadRespuestas = $data['probabilidad_respuestas'];
        $impactoRespuestas = $data['impacto_respuestas'];
        unset($data['probabilidad_respuestas'], $data['impacto_respuestas']);

        $data['probabilidad'] = array_sum($probabilidadRespuestas);
        $data['impacto'] = array_sum($impactoRespuestas);

        $suma = $data['impacto'] + $data['probabilidad'];
        $data['mayor_criticidad'] = $suma >= 14 && $request->boolean('mayor_criticidad');
        $data['user_id'] = Auth::id();
        $data['area_id'] = $data['area_id'] ?? Auth::user()->area_id;
        $riesgo = Riesgo::create($data);
        $riesgo->objetivos()->sync($request->input('objetivos', []));
        $riesgo->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Riesgo creado',
            'estado_id' => Estado::borrador()->id,
            'data' => [
                'tipo' => 'creacion',
                'campos' => [
                    'nombre' => $riesgo->nombre,
                    'descripcion' => $riesgo->descripcion,
                    'impacto' => $riesgo->impacto,
                    'probabilidad' => $riesgo->probabilidad,
                    'mayor_criticidad' => $riesgo->mayor_criticidad,
                    'tipo_riesgo_id' => $riesgo->tipo_riesgo_id,
                ],
                // Respuestas guardadas aparte de "campos": ese array lo recorre la
                // vista de historial esperando valores escalares (ver
                // gestion-actualizaciones.blade.php), y mezclar arrays ahí rompe el render.
                'respuestas' => [
                    'probabilidad' => $probabilidadRespuestas,
                    'impacto' => $impactoRespuestas,
                ],
            ],
        ]);

        return redirect()->route('auditoria.riesgos.show', $riesgo)->with('ok', 'Riesgo creado correctamente.');
    }

    public function show(Riesgo $riesgo)
    {
        $this->authorize('view', $riesgo);
        // controles.estado, planesAccion.estado y planesAccion.tareas.estado: los necesita
        // el accessor valor_residual (sólo mitigan los controles y planes aprobados, y
        // sólo cuando el plan está al 100%).
        $riesgo->load(['tipoRiesgo', 'estado', 'user', 'area', 'controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado']);

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
        // El área actual del riesgo se agrega aunque caiga fuera de la línea del
        // usuario: si no, el select la perdería silenciosamente al guardar.
        $areas = Area::whereIn('id', array_merge(Auth::user()->idsAreasGestionables(), [$riesgo->area_id]))
            ->orderBy('nombre')->get();

        return view('auditoria.riesgo.edit', compact('riesgo', 'tiposRiesgo', 'areas'));
    }

    /**
     * Impacto y probabilidad no se editan acá: son de solo lectura y sólo
     * cambian a través de recalcularStore(), que vuelve a pasar el wizard de
     * preguntas. Evita que se carguen a mano perdiendo la trazabilidad de qué
     * respuestas los originaron.
     */
    public function update(Request $request, Riesgo $riesgo)
    {
        $this->authorize('update', $riesgo);

        if ($riesgo->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.riesgos.show', $riesgo)
                ->with('error', 'El riesgo ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        // Se admite el área que el riesgo ya tenía aunque quede fuera de la línea
        // del usuario: puede gestionarlo por estar asociado a otra de sus gerencias
        // (ver Riesgo::puedeGestionarAlgunaArea()) y no debería verse forzado a moverlo.
        $areasPermitidas = array_merge(Auth::user()->idsAreasGestionables(), [$riesgo->area_id]);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'mayor_criticidad' => 'boolean',
            'respuesta' => Riesgo::reglaRespuesta($request->input('tipo_riesgo_id')),
            'fundamento' => Riesgo::reglaFundamento(),
            'tipo_riesgo_id' => 'required|exists:tipos_riesgo,id',
            'area_id' => ['nullable', 'exists:areas,id', Rule::in($areasPermitidas)],
        ], self::MENSAJES_RESPUESTA + [
            'area_id.in' => 'Sólo puede asignar el riesgo a su área o a una de sus sub-áreas.',
        ]);

        $suma = $riesgo->impacto + $riesgo->probabilidad;
        $data['mayor_criticidad'] = $suma >= 14 && $request->boolean('mayor_criticidad');

        $original = $riesgo->only(array_keys($data));
        $riesgo->update($data);

        $diff = [];
        foreach ($data as $campo => $nuevo) {
            // Los campos con cast a enum (respuesta) llegan de $original como instancia;
            // se desenvuelven a su value para poder compararlos contra el string crudo de $data.
            $antes = $original[$campo] ?? null;
            $antes = $antes instanceof \BackedEnum ? $antes->value : $antes;

            if (array_key_exists($campo, $original) && $antes != $nuevo) {
                $diff[$campo] = ['antes' => $antes, 'despues' => $nuevo];
            }
        }
        if (! empty($diff)) {
            $riesgo->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Borrador modificado',
                'estado_id' => Estado::borrador()->id,
                'data' => ['tipo' => 'edicion', 'diff' => ['campos' => $diff]],
            ]);
        }

        return redirect()->route('auditoria.riesgos.show', $riesgo)->with('ok', 'Riesgo actualizado.');
    }

    public function recalcular(Riesgo $riesgo)
    {
        $this->authorize('update', $riesgo);

        if ($riesgo->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.riesgos.show', $riesgo)
                ->with('error', 'El riesgo ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $preguntas = config('riesgo_preguntas');

        return view('auditoria.riesgo.recalcular', compact('riesgo', 'preguntas'));
    }

    /**
     * Vuelve a pasar el wizard de preguntas (mismo cálculo que store()) para
     * recalcular impacto/probabilidad de un riesgo ya creado, en vez de
     * permitir cargarlos a mano en update(). Registra el cambio como una
     * Actualizacion de tipo 'edicion' con diff, igual que update().
     */
    public function recalcularStore(Request $request, Riesgo $riesgo)
    {
        $this->authorize('update', $riesgo);

        if ($riesgo->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.riesgos.show', $riesgo)
                ->with('error', 'El riesgo ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $data = $request->validate([
            'probabilidad_respuestas' => 'required|array|size:5',
            'probabilidad_respuestas.*' => 'required|integer|min:0|max:2',
            'impacto_respuestas' => 'required|array|size:5',
            'impacto_respuestas.*' => 'required|integer|min:0|max:2',
            'mayor_criticidad' => 'boolean',
        ]);

        $probabilidadRespuestas = $data['probabilidad_respuestas'];
        $impactoRespuestas = $data['impacto_respuestas'];

        $original = $riesgo->only(['impacto', 'probabilidad', 'mayor_criticidad']);

        $nuevos = [];
        $nuevos['probabilidad'] = array_sum($probabilidadRespuestas);
        $nuevos['impacto'] = array_sum($impactoRespuestas);
        $suma = $nuevos['impacto'] + $nuevos['probabilidad'];
        $nuevos['mayor_criticidad'] = $suma >= 14 && $request->boolean('mayor_criticidad');

        $riesgo->update($nuevos);

        $diff = [];
        foreach ($nuevos as $campo => $valor) {
            if ($original[$campo] != $valor) {
                $diff[$campo] = ['antes' => $original[$campo], 'despues' => $valor];
            }
        }
        if (! empty($diff)) {
            $riesgo->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Impacto y probabilidad recalculados',
                'estado_id' => Estado::borrador()->id,
                'data' => [
                    'tipo' => 'edicion',
                    'diff' => ['campos' => $diff],
                    'respuestas' => [
                        'probabilidad' => $probabilidadRespuestas,
                        'impacto' => $impactoRespuestas,
                    ],
                ],
            ]);
        }

        return redirect()->route('auditoria.riesgos.edit', $riesgo)->with('ok', 'Impacto y probabilidad recalculados.');
    }

    public function destroy(Riesgo $riesgo)
    {
        $this->authorize('delete', $riesgo);

        $riesgo->delete();

        return redirect()->route('auditoria.riesgos.index')->with('ok', 'Riesgo eliminado.');
    }

    /**
     * Valida el riesgo y arrastra a "validado" todas sus actualizaciones que
     * seguían en borrador, para que queden listas para aprobar() junto al riesgo.
     */
    public function validar(Riesgo $riesgo)
    {
        $this->authorize('validar', $riesgo);

        $motivos = $riesgo->motivosBloqueoValidacion();
        if (! empty($motivos)) {
            return back()->with('error', implode(' ', $motivos));
        }

        $riesgo->update([
            'estado_id' => Estado::validado()->id,
            'validado_por_id' => Auth::id(),
            'validado_en' => now(),
        ]);

        $riesgo->actualizaciones()
            ->where('estado_id', Estado::borrador()->id)
            ->update(['estado_id' => Estado::validado()->id]);

        $riesgo->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Validado por '.Auth::user()->name,
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'validacion'],
        ]);

        return back()->with('ok', 'Riesgo validado correctamente.');
    }

    /**
     * Aprueba el riesgo y aplica los cambios de todas sus actualizaciones ya
     * validadas (creación y ediciones acumuladas) en una sola transacción.
     * Bloquea si no se cumple Riesgo::motivosBloqueoAprobacion() (ver ahí).
     */
    public function aprobar(Riesgo $riesgo)
    {
        $this->authorize('aprobar', $riesgo);

        $motivos = $riesgo->motivosBloqueoAprobacion();
        if (! empty($motivos)) {
            return back()->with('error', implode(' ', $motivos));
        }

        DB::transaction(function () use ($riesgo) {
            // reorder('id') limpia el latest() de la relación y ordena por id en vez
            // de created_at: la columna es timestamp (precisión de 1 segundo) y dos
            // actualizaciones seguidas pueden empatar, así que created_at solo no
            // desempata de forma confiable. id es autoincremental y sí lo es. Acá
            // importa que la más reciente quede aplicada al final (gana).
            $pendientes = $riesgo->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->reorder('id')
                ->get();

            foreach ($pendientes as $act) {
                $this->aplicarCambiosActualizacion($act, $riesgo);
                $act->update(['estado_id' => Estado::aprobado()->id]);
            }

            $riesgo->update([
                'estado_id' => Estado::aprobado()->id,
                'aprobado_por_id' => Auth::id(),
                'aprobado_en' => now(),
            ]);
            $this->logAprobado($riesgo, 'Aprobado por '.Auth::user()->name);
        });

        return back()->with('ok', 'Riesgo aprobado correctamente.');
    }

    public function rechazar(Riesgo $riesgo)
    {
        $this->authorize('rechazar', $riesgo);

        $riesgo->update([
            'estado_id' => Estado::borrado()->id,
            'rechazado_por_id' => Auth::id(),
            'rechazado_en' => now(),
        ]);
        $this->logAprobado($riesgo, 'Rechazado por '.Auth::user()->name);

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
            'controles' => 'nullable|array',
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
            'objetivos' => 'nullable|array',
            'objetivos.*' => ['exists:objetivos,id', Rule::in(Objetivo::visiblePara(Auth::user())->pluck('id')->toArray())],
        ]);

        $riesgo->objetivos()->sync($request->input('objetivos', []));

        return redirect()->route('auditoria.riesgos.show', $riesgo)->with('ok', 'Objetivos actualizados.');
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
