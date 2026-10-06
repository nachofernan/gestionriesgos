<?php

namespace App\Policies\Auditoria;

use App\Models\Auditoria\Area;
use App\Models\Auditoria\RevisionInicial;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Autorización de la revisión de la carga inicial (D-024), por elemento y por
 * gerencia firmante. Revisa el responsable o quien gestiona el área del
 * elemento dentro de esa gerencia; firma el gerente. Si el responsable es un
 * gerente de esa misma gerencia, sólo esa persona revisa y firma. El comité y
 * el auditor miran, no revisan ni firman. Se usa como
 * `$user->can('firmar', [RevisionInicial::class, $elemento, $gerencia])`.
 */
class RevisionInicialPolicy
{
    /**
     * Revisar es de empleados y coordinadores: un gerente firma o no firma. Si
     * el responsable es gerente de esta gerencia, nadie revisa: sólo ella firma.
     * Tests: el_coordinador_revisa_un_elemento_de_su_area_pero_no_lo_firma,
     * el_gerente_firma_pero_no_revisa, si_el_responsable_es_la_gerente_solo_ella_firma.
     */
    public function revisar(User $user, Model $elemento, Area $gerencia): bool
    {
        if (! $this->habilitada($user, $elemento, $gerencia) || $user->rol === 'gerente') {
            return false;
        }

        if ($this->responsableGerenteDe($elemento, $gerencia)) {
            return false;
        }

        if ($elemento->user_id === $user->id) {
            return true;
        }

        return $user->areaGerencia()?->is($gerencia)
            && $this->areasEnGerencia($elemento, $gerencia)->contains(fn ($areaId) => $user->puedeGestionarArea($areaId));
    }

    /**
     * Tests: el_gerente_firma_en_su_gerencia_y_no_en_otra,
     * si_el_responsable_es_la_gerente_solo_ella_firma.
     */
    public function firmar(User $user, Model $elemento, Area $gerencia): bool
    {
        if (! $this->habilitada($user, $elemento, $gerencia) || $user->rol !== 'gerente') {
            return false;
        }

        if ($responsableGerente = $this->responsableGerenteDe($elemento, $gerencia)) {
            return $responsableGerente->is($user);
        }

        return $user->puedeGestionarArea($gerencia->id);
    }

    /**
     * Condiciones comunes: pantalla activa, usuario con área que no sea comité
     * ni auditor (miran, no firman), la gerencia es una de las que firman el
     * elemento y el usuario puede verlo.
     * Tests: el_comite_y_el_auditor_no_revisan_ni_firman, con_el_flag_apagado_nadie_revisa_ni_firma.
     */
    private function habilitada(User $user, Model $elemento, Area $gerencia): bool
    {
        return config('auditoria.revision_inicial.activa')
            && $user->area_id
            && ! $user->esComite()
            && ! $user->esAuditor()
            && RevisionInicial::gerenciasDe($elemento)->contains('id', $gerencia->id)
            && Gate::forUser($user)->allows('view', $elemento);
    }

    /** El responsable del elemento, si es gerente de esta gerencia. */
    private function responsableGerenteDe(Model $elemento, Area $gerencia): ?User
    {
        $responsable = $elemento->user;

        return $responsable?->rol === 'gerente' && $responsable->areaGerencia()?->is($gerencia)
            ? $responsable
            : null;
    }

    /** Áreas del elemento que caen en esta gerencia (un riesgo puede tener varias). */
    private function areasEnGerencia(Model $elemento, Area $gerencia)
    {
        $areas = $elemento instanceof Riesgo
            ? $elemento->areas->push($elemento->area)->filter()
            : collect([$elemento->area])->filter();

        return $areas->filter(fn (Area $area) => $area->gerencia()?->is($gerencia))->pluck('id')->unique();
    }
}
