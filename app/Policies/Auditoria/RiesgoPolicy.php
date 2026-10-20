<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Riesgo;
use App\Models\User;

/**
 * Autorización de Riesgo por jerarquía de área: gerente gestiona su área y
 * sub-áreas, comité opera en cualquier área (area_id = null). El riesgo
 * pertenece sólo a su area_id, como el resto de las entidades.
 * Tests: RiesgoAccesoPorAreaTest, VisibilidadTest.
 */
class RiesgoPolicy
{
    /**
     * Visibilidad: aprobado y validado son públicos; sin área asignada se ve
     * todo; comité no ve borrador/borrado ajenos (su área es la raíz del árbol,
     * así que puedeGestionarArea() la trataría como ancestro de cualquier otra —
     * hay que cortar antes de llegar ahí); cualquier otro caso requiere
     * gestionar el área del riesgo.
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

        return $user->puedeGestionarArea($riesgo->area_id);
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return ! $user->esAuditor() && $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, Riesgo $riesgo): bool
    {
        return $user->puedeGestionarArea($riesgo->area_id);
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
        return ! $user->esAuditor() && $user->puedeGestionarArea($riesgo->area_id);
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
