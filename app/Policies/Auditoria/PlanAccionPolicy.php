<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\PlanAccion;
use App\Models\User;

/**
 * Autorización de PlanAccion por jerarquía de área: gerente gestiona su área y
 * sub-áreas, comité opera en cualquier área (User::puedeOperarEnArea, D-052).
 */
class PlanAccionPolicy
{
    /**
     * Visibilidad: aprobado y validado son públicos; comité no ve borrador/borrado
     * ajenos (su área es la raíz del árbol, así que puedeGestionarArea() la
     * trataría como ancestro de cualquier otra — hay que cortar antes de llegar
     * ahí); cualquier otro caso requiere gestionar el área efectiva de la entidad
     * (ver areaEfectiva()).
     */
    public function view(User $user, PlanAccion $planAccion): bool
    {
        $estado = $planAccion->estado?->nombre;
        if (in_array($estado, ['aprobado', 'validado'])) {
            return true;
        }
        if (! $user->area_id) {
            return true;
        }
        if ($user->esSoloComite()) {
            return false;
        }

        return $user->puedeGestionarArea($this->areaEfectiva($planAccion));
    }

    public function create(User $user, mixed $areaId = null): bool
    {
        return ! $user->esAuditor() && $user->puedeGestionarArea($areaId);
    }

    public function update(User $user, PlanAccion $planAccion): bool
    {
        return $user->puedeOperarEnArea($this->areaEfectiva($planAccion));
    }

    /** Originar una propuesta o nota; ver RiesgoPolicy::proponer(). */
    public function proponer(User $user, PlanAccion $planAccion): bool
    {
        return $this->update($user, $planAccion)
            || $user->puedeProponerComoAuditor($planAccion->estado?->nombre);
    }

    /**
     * Crear una tarea nueva desde el plan, para cualquier área (D-030): quien
     * gestiona el plan, salvo el auditor, que no crea elementos (D-020).
     * Tests: quien_no_gestiona_el_plan_no_puede_crear_tareas_desde_el, el_auditor_no_crea_tareas_desde_un_plan.
     */
    public function crearTarea(User $user, PlanAccion $planAccion): bool
    {
        return ! $user->esAuditor() && $this->update($user, $planAccion);
    }

    /**
     * Borrado directo (soft delete) de lo que todavía no está aprobado. Lo aprobado
     * no se borra directo: se propone (proponerEliminacion(), D-047, D-058).
     */
    public function delete(User $user, PlanAccion $planAccion): bool
    {
        return ! $user->esAuditor()
            && $user->puedeOperarEnArea($this->areaEfectiva($planAccion))
            && $planAccion->estado?->nombre !== 'aprobado';
    }

    /**
     * Proponer la eliminación de lo aprobado (D-047, D-058): quien gestiona el área,
     * salvo el auditor, si no tiene ya una eliminación pendiente. La aplica el
     * comité al aprobarla; si la propone el comité, se aplica en el acto.
     * Tests: EliminacionPorPropuestaTest.
     */
    public function proponerEliminacion(User $user, PlanAccion $planAccion): bool
    {
        return ! $user->esAuditor()
            && $user->puedeOperarEnArea($this->areaEfectiva($planAccion))
            && $planAccion->motivoBloqueoEliminacion() === null
            && Actualizacion::eliminacionPendienteDe($planAccion) === null;
    }

    /**
     * Un borrador interno no se valida: es gestión propia del área y primero hay
     * que quitarle el flag (D-044). Tests: un_borrador_interno_no_se_valida,
     * al_quitar_el_flag_el_borrador_se_valida.
     */
    public function validar(User $user, PlanAccion $planAccion): bool
    {
        return $user->esGerente()
            && $user->puedeGestionarArea($this->areaEfectiva($planAccion))
            && $planAccion->estado?->nombre === 'borrador'
            && ! $planAccion->interno;
    }

    public function aprobar(User $user, PlanAccion $planAccion): bool
    {
        return $user->esComite()
            && $user->puedeOperarEnArea($this->areaEfectiva($planAccion))
            && $planAccion->estado?->nombre === 'validado';
    }

    /**
     * Ver RiesgoPolicy::rechazar() (D-031). Si un riesgo lo necesita, el
     * controlador lo bloquea (PlanAccion::motivoBloqueoRechazo()). Tests: RechazoComiteTest.
     */
    public function rechazar(User $user, PlanAccion $planAccion): bool
    {
        if ($user->esComite() && $planAccion->estado?->nombre === 'validado') {
            return $user->puedeOperarEnArea($this->areaEfectiva($planAccion));
        }

        return $user->esGerente()
            && $user->puedeGestionarArea($this->areaEfectiva($planAccion))
            && $planAccion->estado?->nombre === 'borrador';
    }

    /**
     * Área contra la que se evalúa el permiso: la propia si la tiene, o si no
     * tiene (area_id null) la de su responsable (user_id) — "sin área" no es
     * "gestionable por cualquiera", manda el responsable y su cadena de mando.
     */
    private function areaEfectiva(PlanAccion $planAccion): ?int
    {
        return $planAccion->area_id ?? $planAccion->user?->area_id;
    }
}
