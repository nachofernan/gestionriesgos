<?php

namespace Database\Seeders\CargaInicial;

use App\Models\Auditoria\PeisItem;
use Illuminate\Database\Seeder;

/**
 * Reemplaza el contenido genérico de PeisItemSeeder.php ("PEIS 1".."PEIS 10")
 * por los 10 ítems reales de PeisItems.csv. La tabla y el pivot ya existían en
 * el código (ver docs/reconstruccion.md sección 8) — esto solo actualiza los
 * datos. A diferencia del seeder original, acá NO se puede deduplicar por
 * `nombre`: el Excel repite el mismo `nombre` (el "Eje") en varias filas que
 * representan objetivos estratégicos distintos dentro de ese eje (la
 * `descripcion`, "OE 1", "OE 2", ...); crear por `nombre` colapsaba los 10
 * ítems reales en solo 4. Cada fila del CSV es un PeisItem propio.
 */
class PeisItemCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    public function run(): void
    {
        foreach ($this->filasCsv('PeisItems.csv') as [$csvId, $nombre, $descripcion]) {
            $item = PeisItem::create([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
            ]);

            MapeoCargaInicial::$peisItemIdPorCsvId[(int) $csvId] = $item->id;
        }
    }
}
