<?php

namespace Database\Seeders\CargaInicial;

use App\Models\Auditoria\Objetivo;
use Illuminate\Database\Seeder;

/**
 * Crea los 78 objetivos de Objetivos.csv y los pivots objetivo_riesgo (solo 24
 * de las 78 filas de ObjetivoRiesgo.csv traen un riesgo_id real, el resto se
 * omite: el objetivo no tiene riesgo asociado todavía en este dataset) y
 * objetivo_peis_item (72 filas, directo).
 */
class ObjetivoCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    /** @var array<int, int> id de Objetivos.csv → id real */
    private array $objetivoIdPorCsvId = [];

    public function run(): void
    {
        $estadoAprobadoId = MapeoCargaInicial::estado('aprobado');

        foreach ($this->filasCsv('Objetivos.csv') as $fila) {
            [$csvId, $nombre, $descripcion, $fechaTxt, $estrategicoTxt, $peisTxt, $areaTxt, $userTxt] = $fila;

            $objetivo = Objetivo::create([
                'nombre' => $nombre,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'fecha_objetivo' => $fechaTxt !== '' ? $this->parsearFechaDdMmYyyy($fechaTxt) : null,
                'estrategico' => strtoupper($estrategicoTxt) === 'SI',
                'peis' => strtoupper($peisTxt) === 'SI',
                'estado_id' => $estadoAprobadoId,
                'user_id' => MapeoCargaInicial::usuario($userTxt),
                'area_id' => MapeoCargaInicial::area($areaTxt),
            ]);

            $this->objetivoIdPorCsvId[(int) $csvId] = $objetivo->id;
        }

        $this->sembrarObjetivoRiesgo();
        $this->sembrarObjetivoPeisItem();
    }

    private function sembrarObjetivoRiesgo(): void
    {
        foreach ($this->filasCsv('ObjetivoRiesgo.csv') as [$objetivoCsvId, $riesgoCsvId]) {
            if ($riesgoCsvId === '') {
                continue; // objetivo sin riesgo asociado todavía, ver docs/reconstruccion.md
            }

            $objetivoId = $this->objetivoIdPorCsvId[(int) $objetivoCsvId] ?? null;
            $riesgoId = MapeoCargaInicial::$riesgoIdPorCsvId[(int) $riesgoCsvId] ?? null;
            if (! $objetivoId || ! $riesgoId) {
                continue;
            }

            Objetivo::find($objetivoId)->riesgos()->syncWithoutDetaching([$riesgoId]);
        }
    }

    private function sembrarObjetivoPeisItem(): void
    {
        foreach ($this->filasCsv('ObjetivoPeisItem.csv') as $fila) {
            [$objetivoCsvId, $peisItemCsvId] = $fila;

            $objetivoId = $this->objetivoIdPorCsvId[(int) $objetivoCsvId] ?? null;
            $peisItemId = MapeoCargaInicial::$peisItemIdPorCsvId[(int) $peisItemCsvId] ?? null;
            if (! $objetivoId || ! $peisItemId) {
                continue;
            }

            Objetivo::find($objetivoId)->peisItems()->syncWithoutDetaching([$peisItemId]);
        }
    }
}
