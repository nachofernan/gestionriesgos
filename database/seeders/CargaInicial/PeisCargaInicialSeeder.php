<?php

namespace Database\Seeders\CargaInicial;

use App\Models\Auditoria\Objetivo;
use Illuminate\Database\Seeder;

/**
 * Crea cada fila de PeisItems.csv como un objetivo PEIS (`es_peis`) aprobado del
 * área Fortalecimiento Institucional (D-054). En el Excel el `nombre` es el Eje,
 * que se repite en varias filas, y la `descripcion` es el objetivo estratégico
 * ("OE 01: ..."), que es lo que lo distingue: por eso el OE pasa a ser el nombre
 * del objetivo y el Eje su descripción.
 */
class PeisCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    public function run(): void
    {
        $estadoAprobadoId = MapeoCargaInicial::estado('aprobado');
        $areaId = MapeoCargaInicial::area('FC');
        $userId = MapeoCargaInicial::usuario('Mercapidez');

        foreach ($this->filasCsv('PeisItems.csv') as [$csvId, $eje, $oe]) {
            $peis = Objetivo::create([
                'nombre' => trim($oe),
                'descripcion' => trim($eje),
                'es_peis' => true,
                'estado_id' => $estadoAprobadoId,
                'user_id' => $userId,
                'area_id' => $areaId,
            ]);

            MapeoCargaInicial::$peisIdPorCsvId[(int) $csvId] = $peis->id;
        }
    }
}
