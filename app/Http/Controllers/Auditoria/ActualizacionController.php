<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestiona el ciclo de vida de las Actualizaciones: propuestas de cambio sobre
 * Riesgo, Control, Objetivo, PlanAccion y Tarea que quedan pendientes hasta ser
 * validadas/aprobadas (lo que aplica los cambios vía Actualizacion::aplicarCambios())
 * o rechazadas. Las transiciones en sí viven en el modelo (marcarValidada/
 * marcarAprobada/marcarRechazada) porque también las usa GestionActualizaciones.
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
        $actualizacion->marcarValidada(Auth::user());

        return back()->with('ok', 'Actualización validada y cambios aplicados.');
    }

    /**
     * Aprueba la actualización y aplica sus cambios sobre la entidad relacionada,
     * independientemente del estado de validación previo.
     */
    public function aprobar(Actualizacion $actualizacion)
    {
        $this->authorize('aprobar', $actualizacion);
        $actualizacion->marcarAprobada(Auth::user());

        return back()->with('ok', 'Actualización aprobada y cambios aplicados.');
    }

    public function rechazar(Actualizacion $actualizacion)
    {
        $this->authorize('rechazar', $actualizacion);
        $actualizacion->marcarRechazada();

        return back()->with('ok', 'Actualización rechazada.');
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
