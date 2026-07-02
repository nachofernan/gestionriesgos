<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Control;
use App\Models\User;

/**
 * Autorización de Control por jerarquía de área: gerente gestiona su área y
 * sub-áreas, comité opera en cualquier área (area_id = null).
 */
class ControlPolicy
{
    /**
     * Visibilidad: aprobado es público; sin área asignada se ve todo; comité sólo
     * ve validado+ (no borradores ajenos); gerente ve todo lo de su área/sub-áreas
     * sin importar el estado; fuera del área propia, sólo lo ya validado
     * (puedeVerEnGerencia); cualquier otro caso cae al chequeo de gestión de área.
     */
    public function view(User $user, Control $control): bool
    {
        $estado = $control->estado?->nombre;
        if ($estado === 'aprobado') return true;
        if (!$user->area_id) return true;
        if ($user->esComite()) return $estado === 'validado';
        if ($user->esGerente()) return $user->puedeGestionarArea($control->area_id);
        if ($estado === 'validado') return $user->puedeVerEnGerencia($control->area_id);
        return $user->puedeGestionarArea($control->area_id);
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, Control $control): bool
    {
        return $user->puedeGestionarArea($control->area_id);
    }

    public function delete(User $user, Control $control): bool
    {
        return $user->puedeGestionarArea($control->area_id);
    }

    public function validar(User $user, Control $control): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($control->area_id)
            && $control->estado?->nombre === 'borrador';
    }

    public function aprobar(User $user, Control $control): bool
    {
        return $user->esComite()
            && $user->puedeGestionarArea($control->area_id)
            && $control->estado?->nombre === 'validado';
    }

    public function rechazar(User $user, Control $control): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($control->area_id)
            && $control->estado?->nombre === 'borrador';
    }
}
