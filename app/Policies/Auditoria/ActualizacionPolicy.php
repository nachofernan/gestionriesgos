<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Riesgo;
use App\Models\User;

/**
 * Autorización sobre transiciones de una Actualizacion individual. La Actualizacion
 * no tiene área propia: los checks de área usan la de la entidad `actualizable`.
 */
class ActualizacionPolicy
{
    /** Sólo un gerente que gestione la entidad relacionada, y sólo si sigue en borrador. */
    public function validar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->esGerente()
            && $this->gestionaEntidad($user, $actualizacion)
            && $actualizacion->estado?->nombre === 'borrador';
    }

    /** Comité, sin restricción de área (aprobar es potestad del comité a nivel global). */
    public function aprobar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->esComite()
            && $actualizacion->estado?->nombre === 'validado';
    }

    /** Igual que validar(): gerente que gestione la entidad relacionada, sólo en borrador. */
    public function rechazar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->esGerente()
            && $this->gestionaEntidad($user, $actualizacion)
            && $actualizacion->estado?->nombre === 'borrador';
    }

    /**
     * Si la entidad relacionada es un Riesgo, cualquiera de sus gerencias asociadas
     * habilita (así el gerente de una gerencia compartida puede votar, no sólo el
     * del área de origen). Para el resto de entidades, se usa su area_id.
     */
    private function gestionaEntidad(User $user, Actualizacion $actualizacion): bool
    {
        $model = $actualizacion->actualizable;

        if ($model instanceof Riesgo) {
            return $model->puedeGestionarAlgunaArea($user);
        }

        return $user->puedeGestionarArea($model?->area_id);
    }

    /** Sólo quien la creó, y sólo mientras sigue en borrador. */
    public function cancelar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->id === $actualizacion->user_id
            && $actualizacion->estado?->nombre === 'borrador';
    }
}
