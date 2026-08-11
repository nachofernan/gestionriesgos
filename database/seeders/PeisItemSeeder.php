<?php

namespace Database\Seeders;

use App\Models\Auditoria\PeisItem;
use Illuminate\Database\Seeder;

class PeisItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'nombre' => 'PEIS 1',
                'descripcion' => 'Fortalecer la cultura de integridad y transparencia en todos los niveles de la organización.',
            ],
            [
                'nombre' => 'PEIS 2',
                'descripcion' => 'Prevenir, detectar y gestionar conflictos de interés en la toma de decisiones.',
            ],
            [
                'nombre' => 'PEIS 3',
                'descripcion' => 'Asegurar canales de denuncia accesibles, confidenciales y libres de represalias.',
            ],
            [
                'nombre' => 'PEIS 4',
                'descripcion' => 'Promover la debida diligencia en la relación con terceros, proveedores y socios de negocio.',
            ],
            [
                'nombre' => 'PEIS 5',
                'descripcion' => 'Garantizar el cumplimiento normativo y la mejora continua del sistema de integridad.',
            ],
        ];

        foreach ($items as $item) {
            PeisItem::updateOrCreate(['nombre' => $item['nombre']], $item);
        }
    }
}
