<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de tipos de Riesgo (clasificación, no tiene ciclo de vida propio).
 * `restringe_respuesta` marca los tipos que no admiten transferir el riesgo a un
 * tercero ni convivir con él (hoy, Corrupción): ver RespuestaRiesgo::restringidas().
 */
class TipoRiesgo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipos_riesgo';

    protected $fillable = [
        'nombre',
        'descripcion',
        'restringe_respuesta',
    ];

    protected $casts = [
        'restringe_respuesta' => 'boolean',
    ];

    public function riesgos(): HasMany
    {
        return $this->hasMany(Riesgo::class, 'tipo_riesgo_id');
    }
}
