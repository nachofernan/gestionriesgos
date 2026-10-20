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
    /** Sólo un gerente que gestione el área de la entidad relacionada, y sólo si sigue en borrador. */
    public function validar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($actualizacion->actualizable?->area_id)
            && $actualizacion->estado?->nombre === 'borrador';
    }

    /** Comité, sin restricción de área (aprobar es potestad del comité a nivel global). */
    public function aprobar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->esComite()
            && $actualizacion->estado?->nombre === 'validado';
    }

    /**
     * Igual que validar(): gerente que gestione la entidad, sólo en borrador (el
     * comité, con area_id null, ya entra por acá: esGerente() y gestiona toda
     * área). Además el comité puede rechazar la propuesta ya validada que espera
     * su aprobación, simétrico con aprobar(): sobre lo que le llega, aprueba o
     * rechaza.
     * Test: comite_puede_rechazar_una_actualizacion_validada_que_espera_su_aprobacion.
     */
    public function rechazar(User $user, Actualizacion $actualizacion): bool
    {
        if ($user->esComite() && $actualizacion->estado?->nombre === 'validado') {
            return true;
        }

        return $this->validar($user, $actualizacion);
    }

    /** Sólo quien la creó, y sólo mientras sigue en borrador. */
    public function cancelar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->id === $actualizacion->user_id
            && $actualizacion->estado?->nombre === 'borrador';
    }
}
