<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ítem del catálogo fijo del Plan Estratégico de Integridad Sostenible (PEIS 1..5).
 * Un Objetivo marcado como `peis` debe seleccionar al menos uno (ver Objetivo::peisItems()).
 */
class PeisItem extends Model
{
    use SoftDeletes;

    protected $table = 'peis_items';

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function objetivos(): BelongsToMany
    {
        return $this->belongsToMany(Objetivo::class, 'objetivo_peis_item')
            ->withTimestamps();
    }
}
