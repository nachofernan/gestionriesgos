<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\PlanAccion;
use App\Models\User;

/**
 * Autorización de PlanAccion por jerarquía de área: gerente gestiona su área y
 * sub-áreas, comité opera en cualquier área (area_id = null).
 */
class PlanAccionPolicy
{
    /**
     * Visibilidad: aprobado y validado son públicos; sin área asignada se ve
     * todo; comité no ve borrador/borrado ajenos (su área es la raíz del árbol,
     * así que puedeGestionarArea() la trataría como ancestro de cualquier otra —
     * hay que cortar antes de llegar ahí); cualquier otro caso requiere
     * gestionar el área de la entidad.
     */
    public function view(User $user, PlanAccion $planAccion): bool
    {
        $estado = $planAccion->estado?->nombre;
        if (in_array($estado, ['aprobado', 'validado'])) return true;
        if (!$user->area_id) return true;
        if ($user->esComite()) return false;
        return $user->puedeGestionarArea($planAccion->area_id);
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, PlanAccion $planAccion): bool
    {
        return $user->puedeGestionarArea($planAccion->area_id);
    }

    public function delete(User $user, PlanAccion $planAccion): bool
    {
        return $user->puedeGestionarArea($planAccion->area_id);
    }

    public function validar(User $user, PlanAccion $planAccion): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($planAccion->area_id)
            && $planAccion->estado?->nombre === 'borrador';
    }

    public function aprobar(User $user, PlanAccion $planAccion): bool
    {
        return $user->esComite()
            && $user->puedeGestionarArea($planAccion->area_id)
            && $planAccion->estado?->nombre === 'validado';
    }

    public function rechazar(User $user, PlanAccion $planAccion): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($planAccion->area_id)
            && $planAccion->estado?->nombre === 'borrador';
    }
}
