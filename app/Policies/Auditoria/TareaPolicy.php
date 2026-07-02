<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Tarea;
use App\Models\User;

/**
 * Autorización de Tarea por jerarquía de área: gerente gestiona su área y
 * sub-áreas, comité opera en cualquier área (area_id = null).
 */
class TareaPolicy
{
    /**
     * Visibilidad: aprobado y validado son públicos; sin área asignada se ve
     * todo; comité no ve borrador/borrado ajenos (su área es la raíz del árbol,
     * así que puedeGestionarArea() la trataría como ancestro de cualquier otra —
     * hay que cortar antes de llegar ahí); cualquier otro caso requiere
     * gestionar el área de la entidad.
     */
    public function view(User $user, Tarea $tarea): bool
    {
        $estado = $tarea->estado?->nombre;
        if (in_array($estado, ['aprobado', 'validado'])) return true;
        if (!$user->area_id) return true;
        if ($user->esComite()) return false;
        return $user->puedeGestionarArea($tarea->area_id);
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, Tarea $tarea): bool
    {
        return $user->puedeGestionarArea($tarea->area_id);
    }

    public function delete(User $user, Tarea $tarea): bool
    {
        return $user->puedeGestionarArea($tarea->area_id);
    }

    public function validar(User $user, Tarea $tarea): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($tarea->area_id)
            && $tarea->estado?->nombre === 'borrador';
    }

    public function aprobar(User $user, Tarea $tarea): bool
    {
        return $user->esComite()
            && $user->puedeGestionarArea($tarea->area_id)
            && $tarea->estado?->nombre === 'validado';
    }

    public function rechazar(User $user, Tarea $tarea): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($tarea->area_id)
            && $tarea->estado?->nombre === 'borrador';
    }
}
