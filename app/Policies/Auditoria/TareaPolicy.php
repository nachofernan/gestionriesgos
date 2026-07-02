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
     * Visibilidad: activo es público; sin área asignada se ve todo; comité sólo
     * ve validado+ (no borradores ajenos); gerente ve todo lo de su área/sub-áreas
     * sin importar el estado; fuera del área propia, sólo lo ya validado
     * (puedeVerEnGerencia); cualquier otro caso cae al chequeo de gestión de área.
     */
    public function view(User $user, Tarea $tarea): bool
    {
        $estado = $tarea->estado?->nombre;
        if ($estado === 'activo') return true;
        if (!$user->area_id) return true;
        if ($user->esComite()) return $estado === 'validado';
        if ($user->esGerente()) return $user->puedeGestionarArea($tarea->area_id);
        if ($estado === 'validado') return $user->puedeVerEnGerencia($tarea->area_id);
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

    public function activar(User $user, Tarea $tarea): bool
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
