<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de Riesgo (clasificación, no tiene ciclo de vida propio).
 */
class TipoRiesgo extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'tipos_riesgo';

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function riesgos(): HasMany
    {
        return $this->hasMany(Riesgo::class, 'tipo_riesgo_id');
    }
}