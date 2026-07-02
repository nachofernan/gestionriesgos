<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Actualizacion;
use App\Models\User;

/**
 * Autorización sobre transiciones de una Actualizacion individual. La Actualizacion
 * no tiene área propia: los checks de área usan la de la entidad `actualizable`.
 */
class ActualizacionPolicy
{
    /** Sólo el gerente del área de la entidad relacionada, y sólo si sigue en borrador. */
    public function validar(User $user, Actualizacion $actualizacion): bool
    {
        $areaId = $actualizacion->actualizable?->area_id;
        return $user->esGerente()
            && $user->puedeGestionarArea($areaId)
            && $actualizacion->estado?->nombre === 'borrador';
    }

    /** Comité, sin restricción de área (activar es potestad del comité a nivel global). */
    public function activar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->esComite()
            && $actualizacion->estado?->nombre === 'validado';
    }

    /** Igual que validar(): gerente del área de la entidad relacionada, sólo en borrador. */
    public function rechazar(User $user, Actualizacion $actualizacion): bool
    {
        $areaId = $actualizacion->actualizable?->area_id;
        return $user->esGerente()
            && $user->puedeGestionarArea($areaId)
            && $actualizacion->estado?->nombre === 'borrador';
    }

    /** Sólo quien la creó, y sólo mientras sigue en borrador. */
    public function cancelar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->id === $actualizacion->user_id
            && $actualizacion->estado?->nombre === 'borrador';
    }
}
