<?php

namespace Database\Seeders\CargaInicial;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Naturaleza;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Sede;
use Database\Seeders\NaturalezaSeeder;
use Illuminate\Database\Seeder;

/**
 * Crea los 161 riesgos de Riesgos.csv. Arrancan todos en estado "aprobado"
 * (decisión de negocio, ver docs/reconstruccion.md): representan controles y
 * planes que ya funcionan hoy en la empresa, no un borrador a revalidar. Los 8
 * riesgos sin impacto/probabilidad en el Excel (111, 142-148) quedan en 0/0,
 * pendientes de completar por el negocio. La columna `codigo` del CSV viene
 * vacía y se ignora: los riesgos no llevan código.
 */
class RiesgoCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    public function run(): void
    {
        $estadoAprobadoId = MapeoCargaInicial::estado('aprobado');
        // El Excel no trae sede: la carga inicial deja cada riesgo en todas (D-029).
        $sedeIds = Sede::pluck('id')->all();
        // Tampoco trae naturaleza: se infiere del tipo, una sola y sin fundamento (D-032).
        $naturalezaIdPorNombre = Naturaleza::pluck('id', 'nombre');

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
            $riesgo->sedes()->sync($sedeIds);
            $riesgo->naturalezas()->sync([$naturalezaIdPorNombre[NaturalezaSeeder::paraTipo($riesgo->tipoRiesgo?->nombre)]]);

            MapeoCargaInicial::$riesgoIdPorCsvId[(int) $csvId] = $riesgo->id;
        }
    }
}
