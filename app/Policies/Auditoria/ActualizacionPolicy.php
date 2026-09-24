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
    /**
     * Sólo un gerente que gestione la entidad relacionada, y sólo si sigue en
     * borrador. Bajo doble validación, además, la gerencia que ya emitió su voto
     * (incluida la del proponente, que vota a favor al proponer) no vuelve a
     * validar: el botón le desaparece y sólo espera al resto (ver yaVoto()).
     */
    public function validar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->esGerente()
            && $this->gestionaEntidad($user, $actualizacion)
            && $actualizacion->estado?->nombre === 'borrador'
            && ! $this->yaVoto($user, $actualizacion);
    }

    /** Comité, sin restricción de área (aprobar es potestad del comité a nivel global). */
    public function aprobar(User $user, Actualizacion $actualizacion): bool
    {
        return $user->esComite()
            && $actualizacion->estado?->nombre === 'validado';
    }

    /**
     * Igual que validar(): gerente que gestione la entidad, sólo en borrador y si su
     * gerencia no votó (el comité, con area_id null, ya entra por acá: esGerente() y
     * gestiona toda área). Además el comité puede rechazar la propuesta ya validada
     * que espera su aprobación, simétrico con aprobar(): sobre lo que le llega,
     * aprueba o rechaza.
     * Tests: comite_puede_rechazar_una_actualizacion_validada_que_espera_su_aprobacion,
     * el_comite_rechaza_una_propuesta_ya_votada_por_todas_las_gerencias_sin_dejar_voto.
     */
    public function rechazar(User $user, Actualizacion $actualizacion): bool
    {
        if ($user->esComite() && $actualizacion->estado?->nombre === 'validado') {
            return true;
        }

        return $user->esGerente()
            && $this->gestionaEntidad($user, $actualizacion)
            && $actualizacion->estado?->nombre === 'borrador'
            && ! $this->yaVoto($user, $actualizacion);
    }

    /**
     * true si, bajo doble validación, la gerencia del usuario ya emitió su voto
     * sobre esta propuesta. Fuera de la doble validación siempre es false (no hay
     * votos por gerencia y rige el flujo de siempre).
     */
    private function yaVoto(User $user, Actualizacion $actualizacion): bool
    {
        if (! $actualizacion->requiereDobleValidacion()) {
            return false;
        }

        $gerencia = $user->areaGerencia();

        return $gerencia
            && $actualizacion->validacionesGerencia()->where('area_id', $gerencia->id)->exists();
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
