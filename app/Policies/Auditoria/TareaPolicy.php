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
     * (ver areaEfectiva()) o gestionar un plan que la contiene: quien crea desde
     * su plan una tarea de otra área la sigue viendo mientras está en borrador
     * (D-030). Ver no habilita editar ni validar.
     * Tests: quien_gestiona_el_plan_ve_la_tarea_en_borrador_de_otra_area,
     * un_tercero_que_no_gestiona_el_plan_no_ve_la_tarea_en_borrador.
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

        return $user->puedeGestionarArea($this->areaEfectiva($tarea))
            || $this->gestionaAlgunPlanDe($user, $tarea);
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
     * Dejar una nota en la tarea: lo de proponer(), más quien gestiona un plan que
     * la contiene, aunque sea de otra gerencia y esté en cualquier estado (para
     * pedirle a la gerencia externa que la complete o la valide). D-027 lo pedía
     * con plan y tarea aprobados; D-030 lo amplía. Solo notas: no habilita
     * proponer cambios. Lo usa Conversacion para modelType 'tarea'.
     * Tests: el_gerente_de_un_plan_aprobado_puede_comentar_una_tarea_aprobada_de_otra_gerencia,
     * quien_gestiona_el_plan_puede_comentar_la_tarea_en_borrador_de_otra_area.
     */
    public function comentar(User $user, Tarea $tarea): bool
    {
        return $this->proponer($user, $tarea) || $this->gestionaAlgunPlanDe($user, $tarea);
    }

    /** true si $user gestiona (update) alguno de los planes que contienen la tarea. */
    private function gestionaAlgunPlanDe(User $user, Tarea $tarea): bool
    {
        return $tarea->planesAccion()->with('user')->get()
            ->contains(fn ($plan) => $user->can('update', $plan));
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
