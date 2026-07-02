<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Objetivo;
use App\Models\User;

/**
 * Autorización de Objetivo por jerarquía de área: gerente gestiona su área y
 * sub-áreas, comité opera en cualquier área (area_id = null).
 */
class ObjetivoPolicy
{
    /**
     * Visibilidad: aprobado es público; sin área asignada se ve todo; comité sólo
     * ve validado+ (no borradores ajenos); gerente ve todo lo de su área/sub-áreas
     * sin importar el estado; fuera del área propia, sólo lo ya validado
     * (puedeVerEnGerencia); cualquier otro caso cae al chequeo de gestión de área.
     */
    public function view(User $user, Objetivo $objetivo): bool
    {
        $estado = $objetivo->estado?->nombre;
        if ($estado === 'aprobado') return true;
        if (!$user->area_id) return true;
        if ($user->esComite()) return $estado === 'validado';
        if ($user->esGerente()) return $user->puedeGestionarArea($objetivo->area_id);
        if ($estado === 'validado') return $user->puedeVerEnGerencia($objetivo->area_id);
        return $user->puedeGestionarArea($objetivo->area_id);
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, Objetivo $objetivo): bool
    {
        return $user->puedeGestionarArea($objetivo->area_id);
    }

    public function delete(User $user, Objetivo $objetivo): bool
    {
        return $user->puedeGestionarArea($objetivo->area_id);
    }

    public function validar(User $user, Objetivo $objetivo): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($objetivo->area_id)
            && $objetivo->estado?->nombre === 'borrador';
    }

    public function aprobar(User $user, Objetivo $objetivo): bool
    {
        return $user->esComite()
            && $user->puedeGestionarArea($objetivo->area_id)
            && $objetivo->estado?->nombre === 'validado';
    }

    public function rechazar(User $user, Objetivo $objetivo): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($objetivo->area_id)
            && $objetivo->estado?->nombre === 'borrador';
    }
}
