<?php

namespace Database\Seeders\CargaInicial;

use App\Models\Auditoria\Control;
use Illuminate\Database\Seeder;

/**
 * Crea los 173 controles de Controles.csv y el pivot control_riesgo de
 * ControlRiesgo.csv. Cuando nombre y descripción vienen idénticos (la gran
 * mayoría de las filas) se descarta la descripción, por indicación de
 * auditoría. Los controles sin mitigacion_default cargada quedan en 0 —decisión
 * de negocio—, no en el default de esquema (1). ControlRiesgo.csv trae
 * duplicados exactos (mismo control_id+riesgo_id repetido); se deduplican acá.
 */
class ControlCargaInicialSeeder extends Seeder
{
    use LeeCsv;

    /** @var array<int, int> id de Controles.csv → id real */
    private array $controlIdPorCsvId = [];

    public function run(): void
    {
        $estadoAprobadoId = MapeoCargaInicial::estado('aprobado');

        foreach ($this->filasCsv('Controles.csv') as [$csvId, $nombre, $descripcion, $mitigacionDefaultTxt, $areaTxt, $userTxt]) {
            $descripcion = $descripcion !== '' && $descripcion !== $nombre ? $descripcion : null;

            $control = Control::create([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'mitigacion_default' => $mitigacionDefaultTxt !== '' ? (int) $mitigacionDefaultTxt : 0,
                'estado_id' => $estadoAprobadoId,
                'user_id' => MapeoCargaInicial::usuario($userTxt),
                'area_id' => MapeoCargaInicial::area($areaTxt),
            ]);

            $this->controlIdPorCsvId[(int) $csvId] = $control->id;
        }

        $this->sembrarControlRiesgo();
    }

    private function sembrarControlRiesgo(): void
    {
        $vistos = [];

        foreach ($this->filasCsv('ControlRiesgo.csv') as [$controlCsvId, $riesgoCsvId, $mitigacionTxt]) {
            $clave = $controlCsvId.'-'.$riesgoCsvId;
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $controlId = $this->controlIdPorCsvId[(int) $controlCsvId] ?? null;
            $riesgoId = MapeoCargaInicial::$riesgoIdPorCsvId[(int) $riesgoCsvId] ?? null;
            if (! $controlId || ! $riesgoId) {
                continue;
            }

            Control::find($controlId)->riesgos()->syncWithoutDetaching([
                $riesgoId => ['mitigacion' => $mitigacionTxt !== '' ? (int) $mitigacionTxt : null],
            ]);
        }
    }
}
