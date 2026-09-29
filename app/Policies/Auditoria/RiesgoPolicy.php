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
     * gestionar alguna de las gerencias asociadas al riesgo (ver
     * Riesgo::puedeGestionarAlgunaArea(), no solo area_id).
     */
    public function view(User $user, Riesgo $riesgo): bool
    {
        $estado = $riesgo->estado?->nombre;
        if (in_array($estado, ['aprobado', 'validado'])) {
            return true;
        }
        if (! $user->area_id) {
            return true;
        }
        if ($user->esComite()) {
            return false;
        }

        return $riesgo->puedeGestionarAlgunaArea($user);
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return ! $user->esAuditor() && $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, Riesgo $riesgo): bool
    {
        return $riesgo->puedeGestionarAlgunaArea($user);
    }

    /**
     * Originar una propuesta de cambio o una nota (ficha, bloques de relaciones,
     * conversación, recálculo). Quien gestiona el riesgo, o un auditor sobre uno
     * validado/aprobado de cualquier gerencia (D-020). No habilita mutar directo:
     * eso sigue siendo update().
     */
    public function proponer(User $user, Riesgo $riesgo): bool
    {
        return $this->update($user, $riesgo)
            || $user->puedeProponerComoAuditor($riesgo->estado?->nombre);
    }

    public function delete(User $user, Riesgo $riesgo): bool
    {
        return ! $user->esAuditor() && $riesgo->puedeGestionarAlgunaArea($user);
    }

    public function validar(User $user, Riesgo $riesgo): bool
    {
        return $user->esGerente()
            && $riesgo->puedeGestionarAlgunaArea($user)
            && $riesgo->estado?->nombre === 'borrador';
    }

    public function aprobar(User $user, Riesgo $riesgo): bool
    {
        return $user->esComite()
            && $riesgo->puedeGestionarAlgunaArea($user)
            && $riesgo->estado?->nombre === 'validado';
    }

    public function rechazar(User $user, Riesgo $riesgo): bool
    {
        return $user->esGerente()
            && $riesgo->puedeGestionarAlgunaArea($user)
            && $riesgo->estado?->nombre === 'borrador';
    }

    /**
     * Gestionar (agregar/quitar) las gerencias asociadas a un riesgo: sólo un
     * gerente de alguna gerencia asociada, y sólo una vez que el riesgo dejó el
     * borrador (validado o aprobado). En borrador el riesgo conserva su única
     * gerencia de origen; compartirlo con otra gerencia es una acción posterior
     * a la validación. Esta regla es la que garantiza que un borrador siempre
     * tenga una sola gerencia, y por lo tanto que la doble validación sólo entre
     * en juego sobre cambios de un riesgo ya validado (ver Riesgo::gerencias()).
     */
    public function gestionarGerencias(User $user, Riesgo $riesgo): bool
    {
        return $user->esGerente()
            && $riesgo->puedeGestionarAlgunaArea($user)
            && in_array($riesgo->estado?->nombre, ['validado', 'aprobado']);
    }
}
