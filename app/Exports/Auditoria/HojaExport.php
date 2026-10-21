<?php

namespace App\Exports\Auditoria;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Una hoja del libro de un reporte: filas ya armadas, con las primeras en negrita como encabezado. */
class HojaExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $titulo,
        private readonly array $filas,
        private readonly int $filasEncabezado = 1,
    ) {}

    public function array(): array
    {
        return $this->filas;
    }

    public function title(): string
    {
        // Excel no admite más de 31 caracteres en el nombre de una hoja.
        return mb_substr($this->titulo, 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $estilos = [];
        for ($i = 1; $i <= $this->filasEncabezado; $i++) {
            $estilos[$i] = ['font' => ['bold' => true]];
        }

        return $estilos;
    }
}
