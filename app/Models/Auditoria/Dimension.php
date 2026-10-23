<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dimensión del riesgo (D-032): derechos humanos, medioambiente,
 * anticorrupción, gobernanza. Catálogo fijo (DimensionSeeder), con ejemplos y
 * las referencias que la definen. Un riesgo tiene una o más. Es informativa:
 * no interviene en la autorización ni en el valor del riesgo.
 */
class Dimension extends Model
{
    use SoftDeletes;

    protected $table = 'dimensiones';

    protected $fillable = ['nombre', 'ejemplos'];

    public function referencias(): HasMany
    {
        return $this->hasMany(DimensionReferencia::class)->orderBy('orden');
    }

    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'dimension_riesgo')->withTimestamps();
    }

    /**
     * Diff entre dos juegos de dimensiones, mismo formato que Sede::diff():
     * ['agrega' => [['id', 'nombre']], 'quita' => [...]], vacío si no cambió nada.
     * Lo usan RiesgoController::update() y FichaRiesgo::guardar().
     */
    public static function diff(array $idsAntes, array $idsDespues): array
    {
        $antes = array_map('intval', $idsAntes);
        $despues = array_map('intval', $idsDespues);
        $nombres = static::withTrashed()->whereIn('id', array_merge($antes, $despues))->pluck('nombre', 'id');
        $items = fn (array $ids) => array_values(array_map(fn ($id) => ['id' => $id, 'nombre' => $nombres[$id] ?? '—'], $ids));

        return array_filter([
            'agrega' => $items(array_diff($despues, $antes)),
            'quita' => $items(array_diff($antes, $despues)),
        ]);
    }
}
