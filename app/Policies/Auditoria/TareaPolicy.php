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
     * Visibilidad: aprobado y validado son públicos; comité no ve borrador/borrado
     * ajenos (su área es la raíz del árbol, así que puedeGestionarArea() la
     * trataría como ancestro de cualquier otra — hay que cortar antes de llegar
     * ahí); cualquier otro caso requiere gestionar el área efectiva de la entidad
     * (ver areaEfectiva()).
     */
    public function view(User $user, Tarea $tarea): bool
    {
        $estado = $tarea->estado?->nombre;
        if (in_array($estado, ['aprobado', 'validado'])) {
            return true;
        }
        if (! $user->area_id) {
            return true;
        }
        if ($user->esComite()) {
            return false;
        }

        return $user->puedeGestionarArea($this->areaEfectiva($tarea));
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return ! $user->esAuditor() && $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, Tarea $tarea): bool
    {
        return $user->puedeGestionarArea($this->areaEfectiva($tarea));
    }

    /** Originar una propuesta o nota; ver RiesgoPolicy::proponer(). */
    public function proponer(User $user, Tarea $tarea): bool
    {
        return $this->update($user, $tarea)
            || $user->puedeProponerComoAuditor($tarea->estado?->nombre);
    }

    /**
     * Dejar una nota en la tarea: lo de proponer(), más quien gestiona un plan
     * aprobado que contiene esta tarea aprobada, aunque sea de otra gerencia
     * (para pedirle a la gerencia externa que la complete). Solo notas: no habilita
     * proponer cambios. Lo usa Conversacion para modelType 'tarea'.
     * Tests: el_gerente_de_un_plan_aprobado_puede_comentar_una_tarea_aprobada_de_otra_gerencia,
     * el_comentario_desde_el_plan_no_habilita_si_el_plan_no_esta_aprobado,
     * el_comentario_desde_el_plan_no_habilita_si_la_tarea_no_esta_aprobada.
     */
    public function comentar(User $user, Tarea $tarea): bool
    {
        if ($this->proponer($user, $tarea)) {
            return true;
        }

        if ($tarea->estado?->nombre !== 'aprobado') {
            return false;
        }

        return $tarea->planesAccion()->with(['estado', 'user'])->get()
            ->contains(fn ($plan) => $plan->estado?->nombre === 'aprobado' && $user->can('update', $plan));
    }

    public function delete(User $user, Tarea $tarea): bool
    {
        return ! $user->esAuditor() && $user->puedeGestionarArea($this->areaEfectiva($tarea));
    }

    public function validar(User $user, Tarea $tarea): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($this->areaEfectiva($tarea))
            && $tarea->estado?->nombre === 'borrador';
    }

    public function aprobar(User $user, Tarea $tarea): bool
    {
        return $user->esComite()
            && $user->puedeGestionarArea($this->areaEfectiva($tarea))
            && $tarea->estado?->nombre === 'validado';
    }

    public function rechazar(User $user, Tarea $tarea): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($this->areaEfectiva($tarea))
            && $tarea->estado?->nombre === 'borrador';
    }

    /**
     * Área contra la que se evalúa el permiso: la propia si la tiene, o si no
     * tiene (area_id null) la de su responsable (user_id) — "sin área" no es
     * "gestionable por cualquiera", manda el responsable y su cadena de mando.
     */
    private function areaEfectiva(Tarea $tarea): ?int
    {
        return $tarea->area_id ?? $tarea->user?->area_id;
    }
}
