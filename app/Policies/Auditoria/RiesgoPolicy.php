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
     * Visibilidad: aprobado y validado son públicos; sin área asignada se ve
     * todo; comité no ve borrador/borrado ajenos (su área es la raíz del árbol,
     * así que puedeGestionarArea() la trataría como ancestro de cualquier otra —
     * hay que cortar antes de llegar ahí); cualquier otro caso requiere
     * gestionar el área de la entidad.
     */
    public function view(User $user, Riesgo $riesgo): bool
    {
        $estado = $riesgo->estado?->nombre;
        if (in_array($estado, ['aprobado', 'validado'])) return true;
        if (!$user->area_id) return true;
        if ($user->esComite()) return false;
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
