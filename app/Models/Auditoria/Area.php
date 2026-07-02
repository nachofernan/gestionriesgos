<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Área organizacional con jerarquía auto-referencial (area_padre_id). Base de la
 * autorización por área: un gerente gestiona su área y todas sus sub-áreas.
 */
class Area extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'areas';

    protected $fillable = [
        'nombre',
        'area_padre_id',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(Area::class, 'area_padre_id');
    }

    /**
     * IDs de esta área más todos sus descendientes, recorriendo `hijos`
     * recursivamente. Se usa para incluir sub-áreas en filtros/autorización.
     */
    public function obtenerIdsSubarbol(): array
    {
        $ids = [$this->id];
        foreach ($this->hijos as $hijo) {
            $ids = array_merge($ids, $hijo->obtenerIdsSubarbol());
        }
        return $ids;
    }

    /**
     * true si $this es igual o ancestro del área con $areaId. Recorre hacia
     * arriba desde $areaId (en vez de hacia abajo desde $this) para no cargar
     * todo el subárbol cuando sólo hace falta esta comprobación puntual.
     */
    public function esAncestroOIgual(int $areaId): bool
    {
        if ($this->id === $areaId) {
            return true;
        }

        $area = static::find($areaId);
        while ($area?->area_padre_id) {
            if ($area->area_padre_id === $this->id) {
                return true;
            }
            $area = static::find($area->area_padre_id);
        }

        return false;
    }
}
