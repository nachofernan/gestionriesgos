<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\User;

class ControlSeeder extends Seeder
{
    public function run(): void
    {
        $users     = User::where('id', '>', 1)->whereNotNull('area_id')->get();
        $riesgoIds = Riesgo::pluck('id')->toArray();
        $estadoIds = Estado::pluck('id', 'nombre');

        // Pool de estados con variedad (antes quedaban todos hardcodeados en "aprobado").
        $pool = ['borrador', 'borrador', 'validado', 'validado', 'validado',
                 'aprobado', 'aprobado', 'aprobado', 'aprobado', 'borrado'];

        // Distribución de controles por riesgo sobre 15 riesgos:
        // 20% (3) → 0 controles
        // 30% (5) → 1 control    (aprox: usamos 4 para llegar a 15 exacto)
        // 40% (6) → 2 controles
        // 10% (2) → 3 controles
        // Total: 3+4+6+2 = 15
        $distribucion = array_merge(
            array_fill(0, 3, 0),
            array_fill(0, 4, 1),
            array_fill(0, 6, 2),
            array_fill(0, 2, 3),
        );
        shuffle($distribucion);

        $controlCounter = 1;

        foreach ($riesgoIds as $idx => $riesgoId) {
            $cantidadControles = $distribucion[$idx] ?? 0;

            for ($j = 0; $j < $cantidadControles; $j++) {
                $user    = $users->random();
                $control = Control::create([
                    'nombre'             => 'Control ' . str_pad($controlCounter, 2, '0', STR_PAD_LEFT),
                    'descripcion'        => 'Descripción del control ' . str_pad($controlCounter, 2, '0', STR_PAD_LEFT),
                    'mitigacion_default' => rand(1, 10),
                    'user_id'            => $user->id,
                    'area_id'            => $user->area_id,
                    'estado_id'          => $estadoIds[$pool[array_rand($pool)]],
                ]);

                $control->riesgos()->attach([
                    $riesgoId => ['mitigacion' => rand(0, 1) ? null : rand(1, 10)],
                ]);

                $controlCounter++;
            }
        }
    }
}
