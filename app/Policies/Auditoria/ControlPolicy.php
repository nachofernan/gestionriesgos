<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Control;
use App\Models\User;

/**
 * Autorización de Control por jerarquía de área: gerente gestiona su área y
 * sub-áreas, comité opera en cualquier área (area_id = null).
 */
class ControlPolicy
{
    /**
     * Visibilidad: aprobado y validado son públicos; comité no ve borrador/borrado
     * ajenos (su área es la raíz del árbol, así que puedeGestionarArea() la
     * trataría como ancestro de cualquier otra — hay que cortar antes de llegar
     * ahí); cualquier otro caso requiere gestionar el área efectiva de la entidad
     * (ver areaEfectiva()).
     */
    public function view(User $user, Control $control): bool
    {
        $estado = $control->estado?->nombre;
        if (in_array($estado, ['aprobado', 'validado'])) return true;
        if (!$user->area_id) return true;
        if ($user->esComite()) return false;
        return $user->puedeGestionarArea($this->areaEfectiva($control));
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, Control $control): bool
    {
        return $user->puedeGestionarArea($this->areaEfectiva($control));
    }

    public function delete(User $user, Control $control): bool
    {
        return $user->puedeGestionarArea($this->areaEfectiva($control));
    }

    public function validar(User $user, Control $control): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($this->areaEfectiva($control))
            && $control->estado?->nombre === 'borrador';
    }

    public function aprobar(User $user, Control $control): bool
    {
        return $user->esComite()
            && $user->puedeGestionarArea($this->areaEfectiva($control))
            && $control->estado?->nombre === 'validado';
    }

    public function rechazar(User $user, Control $control): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($this->areaEfectiva($control))
            && $control->estado?->nombre === 'borrador';
    }

    /**
     * Área contra la que se evalúa el permiso: la propia si la tiene, o si no
     * tiene (area_id null) la de su responsable (user_id) — "sin área" no es
     * "gestionable por cualquiera", manda el responsable y su cadena de mando.
     */
    private function areaEfectiva(Control $control): ?int
    {
        return $control->area_id ?? $control->user?->area_id;
    }
}
