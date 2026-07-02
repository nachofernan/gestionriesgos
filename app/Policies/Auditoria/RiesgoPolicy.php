<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Riesgo;
use App\Models\User;

/**
 * Autorización de Riesgo por jerarquía de área: gerente gestiona su área y
 * sub-áreas, comité opera en cualquier área (area_id = null).
 */
class RiesgoPolicy
{
    /**
     * Visibilidad: aprobado es público; sin área asignada se ve todo; comité sólo
     * ve validado+ (no borradores ajenos); gerente ve todo lo de su área/sub-áreas
     * sin importar el estado; fuera del área propia, sólo lo ya validado
     * (puedeVerEnGerencia); cualquier otro caso cae al chequeo de gestión de área.
     */
    public function view(User $user, Riesgo $riesgo): bool
    {
        $estado = $riesgo->estado?->nombre;
        if ($estado === 'aprobado') return true;
        if (!$user->area_id) return true;
        if ($user->esComite()) return $estado === 'validado';
        if ($user->esGerente()) return $user->puedeGestionarArea($riesgo->area_id);
        if ($estado === 'validado') return $user->puedeVerEnGerencia($riesgo->area_id);
        return $user->puedeGestionarArea($riesgo->area_id);
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, Riesgo $riesgo): bool
    {
        return $user->puedeGestionarArea($riesgo->area_id);
    }

    public function delete(User $user, Riesgo $riesgo): bool
    {
        return $user->puedeGestionarArea($riesgo->area_id);
    }

    public function validar(User $user, Riesgo $riesgo): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($riesgo->area_id)
            && $riesgo->estado?->nombre === 'borrador';
    }

    public function aprobar(User $user, Riesgo $riesgo): bool
    {
        return $user->esComite()
            && $user->puedeGestionarArea($riesgo->area_id)
            && $riesgo->estado?->nombre === 'validado';
    }

    public function rechazar(User $user, Riesgo $riesgo): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($riesgo->area_id)
            && $riesgo->estado?->nombre === 'borrador';
    }
}
