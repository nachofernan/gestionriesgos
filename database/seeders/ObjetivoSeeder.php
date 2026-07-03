<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\User;

class ObjetivoSeeder extends Seeder
{
    public function run(): void
    {
        $users     = User::where('id', '>', 1)->whereNotNull('area_id')->get();
        $estadoIds = Estado::pluck('id', 'nombre');

        $objetivos = [
            ['nombre' => 'Reducir exposición operativa',      'descripcion' => 'Minimizar los riesgos operativos identificados en procesos críticos.'],
            ['nombre' => 'Fortalecer controles financieros',   'descripcion' => 'Asegurar la integridad y exactitud de la información financiera.'],
            ['nombre' => 'Cumplimiento normativo regulatorio', 'descripcion' => 'Garantizar el cumplimiento de todas las regulaciones aplicables.'],
            ['nombre' => 'Protección de activos de información','descripcion' => 'Salvaguardar los activos de información críticos de la organización.'],
            ['nombre' => 'Continuidad del negocio',            'descripcion' => 'Asegurar la operatividad ante eventos disruptivos o emergencias.'],
        ];

        // La mayoría aprobados: los objetivos son prerequisito para validar un riesgo
        // (ver Riesgo::motivosBloqueoValidacion()), conviene que casi todos ya estén
        // disponibles, con algo de variedad para no verlos todos idénticos.
        $estadosObjetivo = ['borrador', 'validado', 'aprobado', 'aprobado', 'aprobado'];

        foreach ($objetivos as $i => $data) {
            $user = $users->random();
            Objetivo::create([
                'nombre'         => $data['nombre'],
                'descripcion'    => $data['descripcion'],
                'fecha_objetivo' => now()->addMonths(rand(3, 18))->toDateString(),
                'user_id'        => $user->id,
                'area_id'        => $user->area_id,
                'estado_id'      => $estadoIds[$estadosObjetivo[$i]],
            ]);
        }

        // El attach a riesgos se hace desde RiesgoSeeder (corre después de este seeder),
        // así se garantiza que todo riesgo validado/aprobado tenga al menos un objetivo.
    }
}
