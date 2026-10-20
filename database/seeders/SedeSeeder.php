<?php

namespace Database\Seeders;

use App\Models\Auditoria\Sede;
use Illuminate\Database\Seeder;

/** Las sedes reales de la empresa (D-029). Idempotente. */
class SedeSeeder extends Seeder
{
    public const SEDES = [
        'Administración Central - La Plata',
        '9 de Julio - Mar del Plata',
        'Oscar Smith - Villa Gesell',
        'Mar de Ajó',
        'Necochea',
    ];

    public function run(): void
    {
        foreach (self::SEDES as $nombre) {
            Sede::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
