<?php

namespace Database\Seeders\CargaInicial;

use App\Models\Auditoria\Tarea;
use Illuminate\Database\Seeder;

/**
 * Crea las 179 tareas de Tareas.csv. Sin porcentaje de avance cargado → 0
 * (decisión de negocio); sin fecha → null, el sistema lo permite y queda para
 * revisión posterior.
 */
class TareaCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    public function run(): void
    {
        $estadoAprobadoId = MapeoCargaInicial::estado('aprobado');

        foreach ($this->filasCsv('Tareas.csv') as $fila) {
            [$csvId, $nombre, $descripcion, $fechaTxt, $avanceTxt, $areaTxt, $userTxt] = $fila;

            $tarea = Tarea::create([
                'nombre' => $nombre,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'fecha' => $fechaTxt !== '' ? $this->parsearFechaDdMmYyyy($fechaTxt) : null,
                'porcentaje_avance' => $avanceTxt !== '' ? (int) $avanceTxt : 0,
                'estado_id' => $estadoAprobadoId,
                'user_id' => MapeoCargaInicial::usuario($userTxt),
                'area_id' => MapeoCargaInicial::area($areaTxt),
            ]);

            MapeoCargaInicial::$tareaIdPorCsvId[(int) $csvId] = $tarea->id;
        }
    }
}
