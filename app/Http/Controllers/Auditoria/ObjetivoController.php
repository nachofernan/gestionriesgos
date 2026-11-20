<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Auditoria\Concerns\OpcionesAreaResponsable;
use App\Http\Controllers\Auditoria\Concerns\ProponeEliminacion;
use App\Http\Controllers\Auditoria\Concerns\RechazaElemento;
use App\Http\Controllers\Controller;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRUD de Objetivo más su ciclo de vida de estados: borrador → validado → aprobado,
 * o borrador/validado → borrado (rechazo). Un objetivo validado ya no se edita acá;
 * los cambios posteriores pasan por el sistema de Actualizaciones.
 */
class ObjetivoController extends Controller
{
    use OpcionesAreaResponsable, ProponeEliminacion, RechazaElemento;

    public function index()
    {
        $objetivos = Objetivo::with(['riesgos', 'user', 'area'])
            ->visiblePara(Auth::user())
            ->join('estados', 'estados.id', '=', 'objetivos.estado_id')
            ->select('objetivos.*')
            ->orderByRaw(Estado::ordenSql())
            ->orderByDesc('objetivos.created_at')
            ->paginate(20);

        return view('auditoria.objetivo.index', compact('objetivos'));
    }

    public function create()
    {
        $this->authorize('create', Objetivo::class);

        $peisDisponibles = $this->peisDisponibles();

        return view('auditoria.objetivo.create', compact('peisDisponibles') + $this->opcionesAreaResponsable());
    }

    public function store(Request $request)
    {
        $this->authorize('create', [Objetivo::class, $request->input('area_id')]);

        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_objetivo' => 'nullable|date',
            'estrategico' => 'boolean',
            'es_peis' => 'boolean',
            ...self::reglasContribucion(),
        ] + $this->reglasAreaResponsable($request), self::MENSAJES_AREA_RESPONSABLE + self::MENSAJES_CONTRIBUCION);
        $data['estrategico'] = $request->boolean('estrategico');
        $data['es_peis'] = $request->boolean('es_peis');

        $data['user_id'] = $data['user_id'] ?? Auth::id();
        $peisIds = $this->peisElegidos($request, $data['es_peis']);
        unset($data['contribuye_peis'], $data['peis_ids']);

        $data['interno'] = $request->boolean('interno');
        $objetivo = Objetivo::create($data);
        $objetivo->peis()->sync($peisIds);

        $data = ['tipo' => 'creacion', 'campos' => [
            'nombre' => $objetivo->nombre,
            'descripcion' => $objetivo->descripcion,
            'fecha_objetivo' => $objetivo->fecha_objetivo?->format('Y-m-d'),
            'estrategico' => $objetivo->estrategico,
            'es_peis' => $objetivo->es_peis,
        ]];
        // La contribución queda en el historial del objetivo y, derivada, en el del PEIS (D-056).
        if ($diffPeis = $this->diffPeis([], $peisIds)) {
            $data['diff'] = ['relaciones' => ['peis' => $diffPeis]];
        }
        $objetivo->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => 'Objetivo creado',
            'estado_id' => Estado::borrador()->id,
            'data' => $data,
        ]);

        return redirect()->route('auditoria.objetivos.show', $objetivo)->with('ok', 'Objetivo creado.');
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
        $objetivo->setRelation('riesgos', Estado::ordenarColeccion($objetivo->riesgos));

        // Comité de la aprobación vigente (D-039), para el encabezado.
        $comite = Actualizacion::comiteVigenteDe($objetivo);

        // Modal de eliminación por propuesta de lo aprobado (D-047, D-058).
        $eliminacion = $this->datosEliminacion($objetivo);

        return view('auditoria.objetivo.show', compact('objetivo', 'comite', 'eliminacion'));
    }

    public function edit(Objetivo $objetivo)
    {
        $this->authorize('update', $objetivo);

        if ($objetivo->estado?->nombre !== 'borrador') {
            return redirect()->route('auditoria.objetivos.show', $objetivo)
                ->with('error', 'El objetivo ya fue validado. Los cambios deben realizarse a través del sistema de actualizaciones.');
        }

        $objetivo->load('peis');
        $peisDisponibles = $this->peisDisponibles();

        return view('auditoria.objetivo.edit', compact('objetivo', 'peisDisponibles')
            + $this->opcionesAreaResponsable($objetivo->area_id, $objetivo->user_id));
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
            ...self::reglasContribucion(),
        ] + $this->reglasAreaResponsable($request, $objetivo->area_id, $objetivo->user_id), self::MENSAJES_AREA_RESPONSABLE + self::MENSAJES_CONTRIBUCION);
        $data['estrategico'] = $request->boolean('estrategico');

        // es_peis se fija al crear (D-054): no viaja en la edición.
        $peisIds = $this->peisElegidos($request, $objetivo->es_peis);
        unset($data['contribuye_peis'], $data['peis_ids']);

        $data['interno'] = $request->boolean('interno');
        $original = $objetivo->only(array_keys($data));
        $peisAntes = $objetivo->peis()->pluck('objetivos.id')->all();
        $objetivo->update($data);
        $objetivo->peis()->sync($peisIds);

        $diff = [];
        foreach ($data as $campo => $nuevo) {
            if (array_key_exists($campo, $original) && $original[$campo] != $nuevo) {
                $diff['campos'][$campo] = ['antes' => $original[$campo], 'despues' => $nuevo];
            }
        }
        if ($diffPeis = $this->diffPeis($peisAntes, $peisIds)) {
            $diff['relaciones'] = ['peis' => $diffPeis];
        }
        if (! empty($diff)) {
            $objetivo->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Borrador modificado',
                'estado_id' => Estado::borrador()->id,
                'data' => ['tipo' => 'edicion', 'diff' => $diff],
            ]);
        }

        return redirect()->route('auditoria.objetivos.show', $objetivo)->with('ok', 'Objetivo actualizado.');
    }

    /**
     * Propuesta de eliminación de lo aprobado (D-047, D-058), con motivo
     * obligatorio. La aplica el comité al aprobarla.
     * Tests: EliminacionPorPropuestaTest.
     */
    public function proponerEliminacion(Request $request, Objetivo $objetivo)
    {
        $this->authorize('proponerEliminacion', $objetivo);

        return $this->registrarPropuestaEliminacion($request, $objetivo, 'objetivos', 'Objetivo eliminado.');
    }

    /** Borrado directo de lo que todavía no está aprobado (ver la Policy). */
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
    public function aprobar(Request $request, Objetivo $objetivo)
    {
        $this->authorize('aprobar', $objetivo);
        $comite = $request->validate(Actualizacion::reglasComite())['comite'] ?? null;

        DB::transaction(function () use ($objetivo, $comite) {
            // reorder('id') limpia el latest() de la relación y ordena por id en vez
            // de created_at: la columna es timestamp (precisión de 1 segundo) y dos
            // actualizaciones seguidas pueden empatar, así que created_at solo no
            // desempata de forma confiable. id es autoincremental y sí lo es. Acá
            // importa que la más reciente quede aplicada al final (gana).
            $pendientes = $objetivo->actualizaciones()
                ->where('estado_id', Estado::validado()->id)
                ->reorder('id')
                ->get();

            foreach ($pendientes as $act) {
                $this->aplicarCambiosActualizacion($act, $objetivo);
                $act->update(['estado_id' => Estado::aprobado()->id]);
            }

            $objetivo->update(['estado_id' => Estado::aprobado()->id]);
            $this->logAprobado($objetivo, 'Aprobado por '.Auth::user()->name, comite: $comite);
        });

        return back()->with('ok', 'Objetivo aprobado correctamente.');
    }

    /** Rechazo con motivo (D-031). No se rechaza si un riesgo vigente lo necesita. */
    public function rechazar(Request $request, Objetivo $objetivo)
    {
        $this->authorize('rechazar', $objetivo);
        $motivo = $this->motivoRechazo($request);

        if ($bloqueo = $objetivo->motivoBloqueoRechazo()) {
            return back()->with('error', $bloqueo);
        }

        $objetivo->update(['estado_id' => Estado::borrado()->id]);
        Actualizacion::registrarRechazo($objetivo, Auth::user(), $motivo);

        return back()->with('ok', 'Objetivo rechazado.');
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

    private const MENSAJES_CONTRIBUCION = [
        'peis_ids.required_if' => 'Seleccioná al menos un objetivo PEIS al que contribuye.',
        'peis_ids.*.exists' => 'Uno de los objetivos elegidos no es un PEIS vigente.',
    ];

    /**
     * "Contribuye al PEIS" es un toggle de la UI: si está tildado, exige al menos un
     * objetivo PEIS no rechazado (D-054). Lo usan store() y update().
     */
    private static function reglasContribucion(): array
    {
        return [
            'contribuye_peis' => 'boolean',
            'peis_ids' => 'required_if:contribuye_peis,1|array|min:1',
            'peis_ids.*' => Rule::exists('objetivos', 'id')
                ->where('es_peis', true)
                ->whereNull('deleted_at')
                ->whereNot('estado_id', Estado::borrado()->id),
        ];
    }

    /** Ids de los PEIS a los que contribuye el objetivo; un PEIS no contribuye a otro PEIS. */
    private function peisElegidos(Request $request, bool $esPeis): array
    {
        return ! $esPeis && $request->boolean('contribuye_peis')
            ? array_map('intval', $request->input('peis_ids', []))
            : [];
    }

    /**
     * Diff de la contribución a PEIS (['agrega' => [[id, nombre]], 'quita' => …]) para el
     * historial del objetivo, con la misma forma que el resto de las relaciones: así
     * ActividadRelacionada lo muestra también en la actividad del PEIS (D-056). Vacío si
     * no cambió.
     * Tests: crear_un_objetivo_que_contribuye_a_un_peis_aparece_en_la_actividad_del_peis,
     * dejar_de_contribuir_a_un_peis_queda_en_el_historial_de_los_dos.
     */
    private function diffPeis(array $antes, array $despues): array
    {
        $nombres = Objetivo::withTrashed()->whereKey(array_merge($antes, $despues))->pluck('nombre', 'id');
        $items = fn (array $ids) => array_values(array_map(fn ($id) => ['id' => $id, 'nombre' => $nombres[$id] ?? '—'], $ids));

        return array_filter([
            'agrega' => $items(array_diff($despues, $antes)),
            'quita' => $items(array_diff($antes, $despues)),
        ]);
    }

    /** Los objetivos PEIS que el usuario puede elegir como destino de su contribución. */
    private function peisDisponibles()
    {
        return Objetivo::where('es_peis', true)
            ->visiblePara(Auth::user())
            ->whereNot('estado_id', Estado::borrado()->id)
            ->orderBy('nombre')
            ->get();
    }
}
