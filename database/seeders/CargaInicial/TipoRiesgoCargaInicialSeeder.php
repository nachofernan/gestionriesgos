<?php

namespace Database\Seeders\CargaInicial;

use App\Models\Auditoria\TipoRiesgo;
use Illuminate\Database\Seeder;

/**
 * Crea los tipos de riesgo desde TipoRiesgos.csv (7 filas). El riesgo id 69 del
 * Excel traía un 8º tipo, literal "estrategico", que no existe en este catálogo:
 * por indicación de auditoría se reasigna a "Operacional" en
 * RiesgoCargaInicialSeeder, no se crea un tipo nuevo acá.
 */
class TipoRiesgoCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    public function run(): void
    {
        foreach ($this->filasCsv('TipoRiesgos.csv') as [$csvId, $nombre, $descripcion]) {
            $tipo = TipoRiesgo::create([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                // Misma regla de negocio que TipoRiesgoSeeder.php: Corrupción no admite
                // "aceptar"/"compartir" (ver RespuestaRiesgo::restringidas()).
                'restringe_respuesta' => $nombre === 'Corrupción',
            ]);

            MapeoCargaInicial::$tipoRiesgoIdPorCsvId[(int) $csvId] = $tipo->id;
        }
    }
}
