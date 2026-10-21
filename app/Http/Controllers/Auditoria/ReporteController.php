<?php

namespace App\Http\Controllers\Auditoria;

use App\Exports\Auditoria\ReporteExport;
use App\Http\Controllers\Controller;
use App\Services\Auditoria\Reportes\FiltrosReporte;
use App\Services\Auditoria\Reportes\ReporteEvolucion;
use App\Services\Auditoria\Reportes\ReportePlanes;
use App\Services\Auditoria\Reportes\ReportePorGerencia;
use App\Services\Auditoria\Reportes\ReportePorTipo;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Descarga en Excel de los reportes del Panel de Riesgos (D-033), con los mismos
 * filtros que la pestaña (viajan en el query string). Sólo lectura: la
 * visibilidad la pone el sesgo gerencial de los reportes, igual que en pantalla.
 */
class ReporteController extends Controller
{
    private const CLASES = [
        'gerencia' => ReportePorGerencia::class,
        'tipo' => ReportePorTipo::class,
        'evolucion' => ReporteEvolucion::class,
        'planes' => ReportePlanes::class,
    ];

    public function exportar(Request $request, string $reporte)
    {
        $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date',
            'tipo' => 'nullable|integer',
        ]);

        $clase = self::CLASES[$reporte];
        $datos = (new $clase($request->user(), FiltrosReporte::desdeArray($request->query())))->datos();

        return Excel::download(new ReporteExport($reporte, $datos), 'reporte-'.$reporte.'-'.now()->format('Ymd').'.xlsx');
    }
}
