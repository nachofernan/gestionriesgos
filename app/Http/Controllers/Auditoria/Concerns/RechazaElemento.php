<?php

namespace App\Http\Controllers\Auditoria\Concerns;

use Illuminate\Http\Request;

/**
 * Motivo obligatorio para rechazar un elemento (D-031). Lo usan los
 * rechazar() de Riesgo, Control, Objetivo, PlanAccion y Tarea, que después
 * registran el rechazo con Actualizacion::registrarRechazo().
 * Test: rechazar_un_elemento_sin_motivo_no_cambia_nada.
 */
trait RechazaElemento
{
    protected function motivoRechazo(Request $request): string
    {
        return $request->validate(
            ['motivo' => 'required|string|min:3|max:2000'],
            ['motivo.required' => 'Contá por qué se rechaza.', 'motivo.min' => 'Contá por qué se rechaza.'],
        )['motivo'];
    }
}
