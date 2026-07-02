<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;

class PlanAccionSeeder extends Seeder
{
    public function run(): void
    {
        $users     = User::where('id', '>', 1)->whereNotNull('area_id')->get();
        $riesgoIds = Riesgo::pluck('id')->toArray();
        $tareaIds  = Tarea::pluck('id')->toArray();

        for ($i = 1; $i <= 4; $i++) {
            $user = $users->random();
            $plan = PlanAccion::create([
                'codigo'      => 'PLAN-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'nombre'      => 'Plan de Acción ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'descripcion' => 'Descripción del plan de acción ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'user_id'     => $user->id,
                'area_id'     => $user->area_id,
                'estado_id'          => 3,
            ]);

            // 1-3 riesgos
            $rSample = (array) array_rand($riesgoIds, rand(1, min(3, count($riesgoIds))));
            $plan->riesgos()->attach(array_map(fn($idx) => $riesgoIds[$idx], $rSample));

            // 3-5 tareas
            $tSample = (array) array_rand($tareaIds, rand(3, min(5, count($tareaIds))));
            $plan->tareas()->attach(array_map(fn($idx) => $tareaIds[$idx], $tSample));
        }
    }
}
