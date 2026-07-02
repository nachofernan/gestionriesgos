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

        $activoId   = Estado::activo()?->id;
        $validadoId = Estado::validado()?->id;

        if ($user->esComite()) {
            return $query->whereIn('estado_id', array_filter([$activoId, $validadoId]));
        }

        $propiaIds = $user->area->obtenerIdsSubarbol();

        if ($user->esGerente()) {
            return $query->where(fn($q) =>
                $q->where('estado_id', $activoId)
                  ->orWhereIn('area_id', $propiaIds)
            );
        }

        // Empleado: activo=todos, validado=gerencia, borrador/borrado=área propia
        $gerencia    = $user->areaGerencia();
        $gerenciaIds = $gerencia ? $gerencia->obtenerIdsSubarbol() : $propiaIds;

        return $query->where(fn($q) =>
            $q->where('estado_id', $activoId)
              ->orWhere(fn($q2) => $q2->where('estado_id', $validadoId)->whereIn('area_id', $gerenciaIds))
              ->orWhere(fn($q2) => $q2->whereNotIn('estado_id', array_filter([$activoId, $validadoId]))->whereIn('area_id', $propiaIds))
        );
    }
}
