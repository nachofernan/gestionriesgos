<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sede de la empresa: la administración central y las generadoras (D-029).
 * Catálogo fijo (SedeSeeder). Es informativa: no interviene en la autorización.
 */
class Sede extends Model
{
    use SoftDeletes;

    protected $table = 'sedes';

    protected $fillable = ['nombre'];

    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'riesgo_sede')->withTimestamps();
    }

    /**
     * Diff entre dos juegos de sedes, con el formato de las relaciones en el
     * historial: ['agrega' => [['id', 'nombre']], 'quita' => [...]]. Vacío si
     * no cambió nada. Lo usan RiesgoController::update() y FichaRiesgo::guardar().
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
