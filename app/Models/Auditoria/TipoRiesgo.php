<?php

namespace App\Models\Auditoria;

use App\Enums\Auditoria\MetodoEvaluacion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de tipos de Riesgo (clasificación, no tiene ciclo de vida propio).
 * `restringe_respuesta` marca los tipos que no admiten transferir el riesgo a un
 * tercero ni convivir con él (hoy, Corrupción): ver RespuestaRiesgo::restringidas().
 * `admite_peis` marca los tipos cuyos riesgos pueden tener objetivos PEIS (hoy,
 * Corrupción; D-054): ver Riesgo::admitePeis().
 * `metodo_evaluacion` dice con qué wizard se calculan impacto y probabilidad de
 * sus riesgos (hoy, sólo Ambiental usa el suyo; D-043).
 */
class TipoRiesgo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tipos_riesgo';

    protected $fillable = [
        'nombre',
        'descripcion',
        'restringe_respuesta',
        'admite_peis',
        'metodo_evaluacion',
    ];

    protected $casts = [
        'restringe_respuesta' => 'boolean',
        'admite_peis' => 'boolean',
        'metodo_evaluacion' => MetodoEvaluacion::class,
    ];

    public function riesgos(): HasMany
    {
        return $this->hasMany(Riesgo::class, 'tipo_riesgo_id');
    }
}
