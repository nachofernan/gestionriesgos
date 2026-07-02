<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Auditoria\Estado;

class EstadoRiesgoSeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['nombre' => 'borrador', 'color' => 'gray'],
            ['nombre' => 'validado', 'color' => 'blue'],
            ['nombre' => 'activo',   'color' => 'green'],
            ['nombre' => 'borrado',  'color' => 'red'],
        ];

        foreach ($estados as $estado) {
            Estado::updateOrCreate(['nombre' => $estado['nombre']], $estado);
        }
    }
}