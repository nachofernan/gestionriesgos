<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona el ciclo de vida de las Actualizaciones: propuestas de cambio sobre
 * Riesgo, Control, Objetivo, PlanAccion y Tarea que quedan pendientes hasta ser
 * validadas/aprobadas (lo que aplica los cambios vía aplicarCambios()) o rechazadas.
 */
class ActualizacionController extends Controller
{
    // -------------------------------------------------------
    // Crear actualizaciones por entidad
    // -------------------------------------------------------

    public function storeRiesgo(Request $request, Riesgo $riesgo)
    {
        $this->authorize('update', $riesgo);
        $this->crearActualizacion($request, $riesgo, ['nombre', 'descripcion', 'impacto', 'probabilidad']);

        return back()->with('ok', 'Actualización registrada.');
    }

    public function storeControl(Request $request, Control $control)
    {
        $this->authorize('update', $control);
        $this->crearActualizacion($request, $control, ['nombre', 'descripcion', 'mitigacion_default']);

        return back()->with('ok', 'Actualización registrada.');
    }

    public function storeObjetivo(Request $request, Objetivo $objetivo)
    {
        $this->authorize('update', $objetivo);
        $this->crearActualizacion($request, $objetivo, ['nombre', 'descripcion', 'fecha_objetivo', 'estrategico', 'anticorrupcion']);

        return back()->with('ok', 'Actualización registrada.');
    }

    public function storePlan(Request $request, PlanAccion $planAccion)
    {
        $this->authorize('update', $planAccion);
        $this->crearActualizacion($request, $planAccion, ['nombre', 'descripcion']);

        return back()->with('ok', 'Actualización registrada.');
    }

    public function storeTarea(Request $request, Tarea $tarea)
    {
        $this->authorize('update', $tarea);
        $this->crearActualizacion($request, $tarea, ['nombre', 'descripcion', 'porcentaje_avance', 'fecha']);

        return back()->with('ok', 'Actualización registrada.');
    }

    // -------------------------------------------------------
    // Transiciones de estado de una actualización
    // -------------------------------------------------------

    /**
     * Valida la actualización y, si la entidad padre ya estaba en estado "validado",
     * la aprueba en el mismo paso (aplicando los cambios) sin esperar una aprobación aparte.
     */
    public function validar(Actualizacion $actualizacion)
    {
        $this->authorize('validar', $actualizacion);

        DB::transaction(function () use ($actualizacion) {
            $actualizacion->update(['estado_id' => Estado::validado()->id]);

            $parent = $actualizacion->actualizable;
            if ($parent?->estado?->nombre === 'validado') {
                $actualizacion->update([
                    'data' => array_merge($actualizacion->data ?? [], ['activated_by' => Auth::user()->name]),
                ]);
                $this->aplicarCambios($actualizacion->fresh());
            }
        });

        return back()->with('ok', 'Actualización validada y cambios aplicados.');
    }

    /**
     * Aprueba la actualización y aplica sus cambios sobre la entidad relacionada,
     * independientemente del estado de validación previo.
     */
    public function aprobar(Actualizacion $actualizacion)
    {
        $this->authorize('aprobar', $actualizacion);

        DB::transaction(function () use ($actualizacion) {
            $actualizacion->update([
                'estado_id' => Estado::aprobado()->id,
                'data'      => array_merge($actualizacion->data ?? [], ['activated_by' => Auth::user()->name]),
            ]);
            $this->aplicarCambios($actualizacion->fresh());
        });

        return back()->with('ok', 'Actualización aprobada y cambios aplicados.');
    }

    public function rechazar(Actualizacion $actualizacion)
    {
        $this->authorize('rechazar', $actualizacion);

        $actualizacion->update(['estado_id' => Estado::borrado()->id]);

        return back()->with('ok', 'Actualización rechazada.');
    }

    // -------------------------------------------------------
    // Helper compartido
    // -------------------------------------------------------

    /**
     * Aplica sobre la entidad relacionada (`actualizable`) los cambios guardados en `data`:
     * actualiza los campos de `data['campos']` (o el objeto completo si viene en formato
     * legacy sin las claves `campos`/`relaciones`) y sincroniza las relaciones many-to-many
     * indicadas en `data['relaciones']` (sync/attach/detach). No hace nada si `data` está
     * vacío o si el tipo de actualización no es 'cambio'.
     */
    private function aplicarCambios(Actualizacion $actualizacion): void
    {
        $data = $actualizacion->data ?? [];
        if (empty($data)) return;

        $tipo = $data['tipo'] ?? null;
        if ($tipo !== null && $tipo !== 'cambio') return;

        $model = $actualizacion->actualizable;

        if (isset($data['campos']) || isset($data['relaciones'])) {
            if (!empty($data['campos'])) {
                $model->update($data['campos']);
            }
        } else {
            // Legacy format
            $model->update($data);
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
     * Crea la Actualizacion asociada a $model, capturando sólo los campos de
     * $camposPermitidos que vinieron en el request con valor no vacío.
     */
    private function crearActualizacion(Request $request, $model, array $camposPermitidos): void
    {
        $validated = $request->validate([
            'mensaje' => 'required|string|min:3',
        ]);

        // Capturar sólo los campos permitidos que hayan sido enviados con valor
        $cambios = collect($camposPermitidos)
            ->filter(fn($campo) => $request->has($campo) && $request->input($campo) !== null && $request->input($campo) !== '')
            ->mapWithKeys(fn($campo) => [$campo => $request->input($campo)])
            ->toArray();

        $model->actualizaciones()->create([
            'user_id' => Auth::id(),
            'mensaje' => $validated['mensaje'],
            'data'    => empty($cambios) ? null : $cambios,
        ]);
    }
}
