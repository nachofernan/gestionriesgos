<?php

namespace App\Http\Controllers\Auditoria;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\Tarea;
use Illuminate\Http\Request;

/**
 * Registra actualizaciones de avance de una Tarea. A diferencia de
 * ActualizacionController::storeTarea(), acá el porcentaje se aplica de
 * inmediato (no queda pendiente de validación/activación).
 */
class ActualizacionTareaController extends Controller
{
    public function store(Request $request, Tarea $tarea)
    {
        $this->authorize('update', $tarea);

        $validated = $request->validate([
            'mensaje'   => 'required|string|min:3',
            'porcentaje' => 'required|integer|min:0|max:100',
        ]);

        $tarea->actualizaciones()->create([
            'user_id' => auth()->id(),
            'mensaje' => $validated['mensaje'],
            'data'    => ['porcentaje' => $validated['porcentaje']],
        ]);

        $tarea->update(['porcentaje_avance' => $validated['porcentaje']]);

        return redirect()->route('auditoria.tareas.show', $tarea)
            ->with('success', 'Actualización registrada correctamente');
    }
}
