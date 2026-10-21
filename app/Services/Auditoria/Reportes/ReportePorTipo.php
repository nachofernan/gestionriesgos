<?php

namespace App\Services\Auditoria\Reportes;

/**
 * Reporte (b): riesgos por tipo, con el total y los cortes por criticidad
 * inherente/residual, situación de plan y gerencia.
 * Tests: ReportesTest (el_reporte_por_tipo_*).
 */
class ReportePorTipo extends Reporte
{
    public function datos(): array
    {
        $riesgos = $this->riesgos();

        $filas = $riesgos
            ->groupBy(fn ($r) => $r->tipoRiesgo?->nombre ?? 'Sin tipo')
            ->sortKeys()
            ->map(fn ($grupo, $tipo) => ['nombre' => $tipo] + $this->conteos($grupo))
            ->values()
            ->all();

        $gerencias = $riesgos->map(fn ($r) => $this->gerenciaDe($r->area_id))->unique()->sort()->values()->all();

        return [
            'encabezado' => 'Tipo de riesgo',
            'gerencias' => $gerencias,
            'grupos' => [
                ['clave' => 'inherente', 'titulo' => 'Criticidad inherente', 'columnas' => self::CRITICIDADES],
                ['clave' => 'residual', 'titulo' => 'Criticidad residual', 'columnas' => self::CRITICIDADES],
                ['clave' => 'plan', 'titulo' => 'Plan de acción', 'columnas' => self::SITUACIONES_PLAN],
                ['clave' => 'gerencias', 'titulo' => 'Por gerencia', 'columnas' => array_combine($gerencias, $gerencias)],
            ],
            'filas' => $filas,
            'totales' => ['nombre' => 'Total'] + $this->conteos($riesgos),
        ];
    }
}
