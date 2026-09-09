<?php

namespace Database\Seeders\CargaInicial;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Riesgo;
use Illuminate\Database\Seeder;

/**
 * Crea los 161 riesgos de Riesgos.csv. Arrancan todos en estado "aprobado"
 * (decisión de negocio, ver docs/reconstruccion.md): representan controles y
 * planes que ya funcionan hoy en la empresa, no un borrador a revalidar. Los 8
 * riesgos sin impacto/probabilidad en el Excel (111, 142-148) quedan en 0/0,
 * pendientes de completar por el negocio. El `codigo` (R-0001...) no viene del
 * CSV, lo autogenera Riesgo::booted().
 */
class RiesgoCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    public function run(): void
    {
        $estadoAprobadoId = MapeoCargaInicial::estado('aprobado');

        foreach ($this->filasCsv('Riesgos.csv') as $fila) {
            [$csvId, , $nombre, $descripcion, $impacto, $probabilidad, $respuestaTxt, $fundamento, $tipoTxt, $areaTxt, $userTxt, $mayorCriticidadTxt] = $fila;

            $tipoRiesgoId = is_numeric($tipoTxt)
                ? MapeoCargaInicial::$tipoRiesgoIdPorCsvId[(int) $tipoTxt]
                : MapeoCargaInicial::$tipoRiesgoIdPorCsvId[1]; // "estrategico" (fila 69) → Operacional

            $riesgo = Riesgo::create([
                'nombre' => $nombre,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'impacto' => $impacto !== '' ? (int) $impacto : 0,
                'probabilidad' => $probabilidad !== '' ? (int) $probabilidad : 0,
                'mayor_criticidad' => strtoupper($mayorCriticidadTxt) === 'SI',
                'respuesta' => RespuestaRiesgo::from(strtolower(trim($respuestaTxt))),
                'fundamento' => $fundamento !== '' ? $fundamento : null,
                'tipo_riesgo_id' => $tipoRiesgoId,
                'estado_id' => $estadoAprobadoId,
                'user_id' => MapeoCargaInicial::usuario($userTxt),
                'area_id' => MapeoCargaInicial::area($areaTxt),
            ]);

            MapeoCargaInicial::$riesgoIdPorCsvId[(int) $csvId] = $riesgo->id;
        }
    }
}
