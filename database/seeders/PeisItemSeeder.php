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
                'descripcion' => 'Recertificar exitosamente el SGAC de Administración Central en 2025 y 2028, y certificar Central Oscar Smith en 2028',
            ],
            [
                'nombre' => 'PEIS 2',
                'descripcion' => 'Mantener de manera exitosa la certificación del SGAC',
            ],
            [
                'nombre' => 'PEIS 3',
                'descripcion' => 'Lograr una participación creciente de los colaboradores, con incremento del 10% interanual',
            ],
            [
                'nombre' => 'PEIS 4',
                'descripcion' => 'Capacitar para mantener cultura de integridad. Incremento del 3% interanual en capacitaciones',
            ],
            [
                'nombre' => 'PEIS 5',
                'descripcion' => 'Mejorar la difusión interna/externa con incremento del 5% interanual',
            ],
            [
                'nombre' => 'PEIS 6',
                'descripcion' => 'Mantener vigente la normativa del SGAC con revisión del 100% al 2030',
            ],
            [
                'nombre' => 'PEIS 7',
                'descripcion' => 'Mantener adhesión al Pacto Global Argentina con presentación anual de COP',
            ],
            [
                'nombre' => 'PEIS 8',
                'descripcion' => 'Adherir a Forward Faster, avance del 20% anual en metas ODS',
            ],
            [
                'nombre' => 'PEIS 9',
                'descripcion' => 'Cumplir compromisos como miembro de directorio 2024-2026 y volver a aplicar en 2026',
            ],
            [
                'nombre' => 'PEIS 10',
                'descripcion' => 'Desarrollar e implementar iniciativas conjuntas anticorrupción',
            ],
        ];

        foreach ($items as $item) {
            PeisItem::updateOrCreate(['nombre' => $item['nombre']], $item);
        }
    }
}
