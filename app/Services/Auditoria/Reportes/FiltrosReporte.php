<?php

namespace App\Services\Auditoria\Reportes;

use Illuminate\Support\Carbon;

/**
 * Filtros comunes a todos los reportes del panel. Los arma el componente
 * PanelRiesgos desde sus propiedades y ReporteController desde el query string,
 * para que la pantalla y el Excel muestren exactamente lo mismo.
 */
final class FiltrosReporte
{
    public readonly Carbon $desde;

    public readonly Carbon $hasta;

    public function __construct(
        public readonly bool $soloAprobados = true,
        public readonly ?int $tipoId = null,
        public readonly ?string $respuesta = null,
        ?Carbon $desde = null,
        ?Carbon $hasta = null,
    ) {
        $this->desde = ($desde ?? today()->subMonths(3))->copy()->startOfDay();
        $this->hasta = ($hasta ?? today())->copy()->endOfDay();
    }

    /**
     * Desde el array de parámetros que viaja en la URL de descarga (o desde las
     * propiedades del componente). Los strings vacíos son "sin filtro".
     */
    public static function desdeArray(array $datos): self
    {
        $fecha = fn ($v) => filled($v) ? Carbon::parse($v) : null;

        return new self(
            soloAprobados: filter_var($datos['soloAprobados'] ?? true, FILTER_VALIDATE_BOOL),
            tipoId: filled($datos['tipo'] ?? null) ? (int) $datos['tipo'] : null,
            respuesta: filled($datos['respuesta'] ?? null) ? $datos['respuesta'] : null,
            desde: $fecha($datos['desde'] ?? null),
            hasta: $fecha($datos['hasta'] ?? null),
        );
    }

    public function aArray(): array
    {
        return [
            'soloAprobados' => $this->soloAprobados ? 1 : 0,
            'tipo' => $this->tipoId,
            'respuesta' => $this->respuesta,
            'desde' => $this->desde->toDateString(),
            'hasta' => $this->hasta->toDateString(),
        ];
    }
}
