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
     * Visibilidad: aprobado y validado son públicos; sin área asignada se ve
     * todo; comité no ve borrador/borrado ajenos (su área es la raíz del árbol,
     * así que puedeGestionarArea() la trataría como ancestro de cualquier otra —
     * hay que cortar antes de llegar ahí); cualquier otro caso requiere
     * gestionar el área de la entidad.
     */
    public function view(User $user, Objetivo $objetivo): bool
    {
        $estado = $objetivo->estado?->nombre;
        if (in_array($estado, ['aprobado', 'validado'])) return true;
        if (!$user->area_id) return true;
        if ($user->esComite()) return false;
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
