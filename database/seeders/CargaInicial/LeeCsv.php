<?php

namespace Database\Seeders\CargaInicial;

use RuntimeException;

/**
 * Lee un CSV de docs/datos/ (separador ";", con encabezado) y devuelve las filas
 * de datos como arrays indexados, salteando la fila de encabezado. Los headers de
 * estos CSV vienen sucios (columnas vacías al final, nombres con espacios como
 * "riesgo id"), así que no conviene mapear por nombre de columna: cada seeder
 * accede por índice numérico y documenta qué significa cada uno en el destructure.
 */
trait LeeCsv
{
    protected function filasCsv(string $archivo): array
    {
        $ruta = base_path("docs/datos/{$archivo}");
        $handle = fopen($ruta, 'r');
        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir {$ruta}");
        }

        $filas = [];
        $esEncabezado = true;
        while (($fila = fgetcsv($handle, 0, ';', '"')) !== false) {
            if ($esEncabezado) {
                $esEncabezado = false;

                continue;
            }

            // Filas completamente vacías (cola del export de Excel).
            if (count(array_filter($fila, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $filas[] = array_map(fn ($v) => $v === null ? '' : trim($v), $fila);
        }
        fclose($handle);

        return $filas;
    }

    /**
     * "dd/mm/yyyy" (formato usado en Objetivos.csv/Tareas.csv) → "Y-m-d". Devuelve
     * null ante cualquier formato inesperado, para no romper la carga por una
     * fecha mal tipeada en el Excel.
     */
    protected function parsearFechaDdMmYyyy(string $texto): ?string
    {
        $partes = explode('/', $texto);
        if (count($partes) !== 3) {
            return null;
        }
        [$d, $m, $y] = $partes;

        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
    }
}
