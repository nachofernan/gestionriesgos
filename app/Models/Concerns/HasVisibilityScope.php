<?php

namespace App\Models\Concerns;

use App\Models\Auditoria\Estado;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasVisibilityScope
{
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if (!$user->area_id) return $query;

        $publicoIds = array_filter([Estado::aprobado()?->id, Estado::validado()?->id]);

        if ($user->esComite()) {
            return $query->whereIn('estado_id', $publicoIds);
        }

        $propiaIds = $user->area->obtenerIdsSubarbol();

        // Aprobado y validado son públicos para cualquiera; borrador/borrado sólo área propia.
        return $query->where(fn($q) =>
            $q->whereIn('estado_id', $publicoIds)
              ->orWhereIn('area_id', $propiaIds)
        );
    }
}
