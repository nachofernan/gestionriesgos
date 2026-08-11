<?php

namespace App\Models\Concerns;

use App\Models\Auditoria\Estado;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasVisibilityScope
{
    /**
     * Aprobado y validado son públicos para cualquiera; borrador/borrado se ve si
     * el área propia cae en la entidad, o si la entidad no tiene área (area_id
     * null) pero su responsable (user_id) sí cae en el área que este usuario
     * gestiona — "sin área" no es "público", manda el responsable y su cadena de
     * mando (ver VisibilidadSinAreaTest).
     */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if (!$user->area_id) return $query;

        $publicoIds = array_filter([Estado::aprobado()?->id, Estado::validado()?->id]);

        if ($user->esComite()) {
            return $query->whereIn('estado_id', $publicoIds);
        }

        $propiaIds = $user->area->obtenerIdsSubarbol();

        return $query->where(fn($q) =>
            $q->whereIn('estado_id', $publicoIds)
              ->orWhereIn('area_id', $propiaIds)
              ->orWhere(fn($q2) =>
                  $q2->whereNull('area_id')
                     ->whereHas('user', fn($uq) => $uq->whereIn('area_id', $propiaIds))
              )
        );
    }
}
