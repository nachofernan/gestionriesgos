<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use App\Models\User;

class ObjetivoSeeder extends Seeder
{
    public function run(): void
    {
        $users     = User::where('id', '>', 1)->whereNotNull('area_id')->get();
        $riesgoIds = Riesgo::pluck('id')->toArray();

        $objetivos = [
            ['nombre' => 'Reducir exposición operativa',      'descripcion' => 'Minimizar los riesgos operativos identificados en procesos críticos.'],
            ['nombre' => 'Fortalecer controles financieros',   'descripcion' => 'Asegurar la integridad y exactitud de la información financiera.'],
            ['nombre' => 'Cumplimiento normativo regulatorio', 'descripcion' => 'Garantizar el cumplimiento de todas las regulaciones aplicables.'],
            ['nombre' => 'Protección de activos de información','descripcion' => 'Salvaguardar los activos de información críticos de la organización.'],
            ['nombre' => 'Continuidad del negocio',            'descripcion' => 'Asegurar la operatividad ante eventos disruptivos o emergencias.'],
        ];

        foreach ($objetivos as $data) {
            $user    = $users->random();
            $objetivo = Objetivo::create([
                'nombre'         => $data['nombre'],
                'descripcion'    => $data['descripcion'],
                'fecha_objetivo' => now()->addMonths(rand(3, 18))->toDateString(),
                'user_id'        => $user->id,
                'area_id'        => $user->area_id,
                'estado_id'          => 3,
            ]);

            $count  = rand(2, min(4, count($riesgoIds)));
            $keys   = (array) array_rand($riesgoIds, $count);
            $objetivo->riesgos()->attach(array_map(fn($k) => $riesgoIds[$k], $keys));
        }
    }
}
