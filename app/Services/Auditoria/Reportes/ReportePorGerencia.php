<?php

namespace App\Services\Auditoria\Reportes;

use App\Models\Auditoria\Naturaleza;
use App\Models\Auditoria\TipoRiesgo;

/**
 * Reporte (a): riesgos por gerencia, con el total y los cortes por tipo,
 * naturaleza, criticidad inherente/residual y situación de plan.
 * Tests: ReportesTest (el_reporte_por_gerencia_*).
 */
class ReportePorGerencia extends Reporte
{
    public function datos(): array
    {
        $riesgos = $this->riesgos();

        $filas = $riesgos
            ->groupBy(fn ($r) => $this->gerenciaDe($r->area_id))
            ->sortKeys()
            ->map(fn ($grupo, $gerencia) => ['nombre' => $gerencia] + $this->conteos($grupo))
            ->values()
            ->all();

        $tipos = TipoRiesgo::orderBy('nombre')->pluck('nombre')->all();
        $naturalezas = Naturaleza::orderBy('id')->pluck('nombre')->all();

        return [
            'encabezado' => 'Gerencia',
            'grupos' => [
                ['clave' => 'tipos', 'titulo' => 'Por tipo', 'columnas' => array_combine($tipos, $tipos)],
                ['clave' => 'naturalezas', 'titulo' => 'Por naturaleza', 'columnas' => array_combine($naturalezas, $naturalezas)],
                ['clave' => 'inherente', 'titulo' => 'Criticidad inherente', 'columnas' => self::CRITICIDADES],
                ['clave' => 'residual', 'titulo' => 'Criticidad residual', 'columnas' => self::CRITICIDADES],
                ['clave' => 'plan', 'titulo' => 'Plan de acción', 'columnas' => self::SITUACIONES_PLAN],
            ],
            'filas' => $filas,
            'totales' => ['nombre' => 'Total'] + $this->conteos($riesgos),
        ];
    }
}
