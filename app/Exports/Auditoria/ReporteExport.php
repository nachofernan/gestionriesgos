<?php

namespace App\Exports\Auditoria;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Libro Excel de un reporte del panel (D-033). Recibe los mismos datos que la
 * pestaña (app/Services/Auditoria/Reportes) y sólo los aplana en hojas: no
 * calcula nada. Lo descarga ReporteController::exportar.
 */
class ReporteExport implements Export, WithMultipleSheets
{
    public function __construct(
        private readonly string $reporte,
        private readonly array $datos,
    ) {}

    public function sheets(): array
    {
        return match ($this->reporte) {
            'gerencia' => [$this->hojaCruzada('Por gerencia', 'Las columnas por dimensión no suman el total: un riesgo puede tener más de una.')],
            'tipo' => [$this->hojaCruzada('Por tipo')],
            'evolucion' => $this->hojasEvolucion(),
            'planes' => $this->hojasPlanes(),
        };
    }

    /** Tabla cruzada de los reportes (a) y (b): dos filas de encabezado (grupo y columna). */
    private function hojaCruzada(string $titulo, ?string $nota = null): HojaExport
    {
        $grupos = [$this->datos['encabezado'], 'Total'];
        $columnas = ['', ''];
        foreach ($this->datos['grupos'] as $g) {
            $primera = true;
            foreach ($g['columnas'] as $etiqueta) {
                $grupos[] = $primera ? $g['titulo'] : '';
                $columnas[] = $etiqueta;
                $primera = false;
            }
        }

        $fila = function (array $f) {
            $celdas = [$f['nombre'], $f['total']];
            foreach ($this->datos['grupos'] as $g) {
                foreach (array_keys($g['columnas']) as $clave) {
                    $celdas[] = $f[$g['clave']][$clave] ?? 0;
                }
            }

            return $celdas;
        };

        $filas = [$grupos, $columnas];
        foreach ($this->datos['filas'] as $f) {
            $filas[] = $fila($f);
        }
        $filas[] = $fila($this->datos['totales']);
        if ($nota) {
            $filas[] = [];
            $filas[] = [$nota];
        }

        return new HojaExport($titulo, $filas, 2);
    }

    private function hojasEvolucion(): array
    {
        $d = $this->datos;
        $movimiento = fn (array $filas, string $encabezado) => array_merge(
            [[$encabezado, 'Aprobados al inicio', 'Aprobados en el período', 'Rechazados en el período', 'Aprobados al final']],
            array_map(fn ($f) => [$f['nombre'], $f['inicio'], $f['aprobados'], $f['rechazados'], $f['final']], $filas),
        );

        $resumen = [
            ['Período', $d['desde']->format('d/m/Y').' al '.$d['hasta']->format('d/m/Y')],
            ['Aprobados al inicio', $d['resumen']['inicio']],
            ['Aprobados en el período', $d['resumen']['aprobados']],
            ['Rechazados en el período', $d['resumen']['rechazados']],
            ['Aprobados al final', $d['resumen']['final']],
        ];
        if ($d['sinFecha'] > 0) {
            $resumen[] = [];
            $resumen[] = ["{$d['sinFecha']} riesgos aprobados no tienen fecha de aprobación registrada (carga inicial): se cuentan como aprobados antes del período."];
        }

        return [
            new HojaExport('Resumen', $resumen, 0),
            new HojaExport('Por tipo', $movimiento($d['porTipo'], 'Tipo de riesgo')),
            new HojaExport('Por gerencia', $movimiento($d['porGerencia'], 'Gerencia')),
            new HojaExport('Aprobados en el período', array_merge(
                [['Riesgo', 'Tipo', 'Gerencia', 'Aprobado el', 'Valor inherente']],
                array_map(fn ($r) => [$r['nombre'], $r['tipo'], $r['gerencia'], $r['aprobado_en']->format('d/m/Y'), $r['valor_total']], $d['aprobados']),
            )),
            new HojaExport('Avance de planes', array_merge(
                [['Gerencia', 'Plan de acción', 'Avance al inicio (%)', 'Avance al final (%)', 'Variación', 'Tareas completadas']],
                array_map(fn ($p) => [$p['gerencia'], $p['nombre'], $p['avance_inicio'], $p['avance_final'], $p['variacion'], $p['tareas_completadas']], $d['planes']),
            )),
        ];
    }

    private function hojasPlanes(): array
    {
        $d = $this->datos;

        return [
            new HojaExport('Por gerencia', array_merge(
                [['Gerencia', 'Planes', 'En fecha', 'Vencidos', 'Con avance', 'Sin avance', 'Riesgos que mitigan', 'Tareas', 'Tareas vencidas', 'Tareas con avance', 'Tareas sin avance']],
                array_map(fn ($g) => [
                    $g['nombre'], $g['planes'], $g['planes_en_fecha'], $g['planes_vencidos'], $g['planes_con_avance'],
                    $g['planes_sin_avance'], $g['riesgos'], $g['tareas'], $g['tareas_vencidas'], $g['tareas_con_avance'], $g['tareas_sin_avance'],
                ], $d['gerencias']),
            )),
            new HojaExport('Planes', array_merge(
                [['Gerencia', 'Plan de acción', 'Estado', 'Riesgos que mitiga', 'Avance (%)', 'Vencimiento', 'Situación']],
                array_map(fn ($p) => [
                    $p['gerencia'], $p['nombre'], $p['estado'], implode("\n", $p['riesgos']), $p['avance'],
                    $p['vencimiento']?->format('d/m/Y'), $p['vencido'] ? 'Vencido' : 'En fecha',
                ], $d['planes']),
            )),
            new HojaExport('Tareas', array_merge(
                [['Gerencia', 'Plan de acción', 'Tarea', 'Estado', 'Fecha', 'Avance (%)', 'Situación']],
                array_map(fn ($t) => [
                    $t['gerencia'], $t['plan'], $t['nombre'], $t['estado'], $t['fecha']?->format('d/m/Y'), $t['avance'],
                    $t['avance'] >= 100 ? 'Completa' : ($t['vencida'] ? 'Vencida' : 'En fecha'),
                ], $d['tareas']),
            )),
        ];
    }
}
