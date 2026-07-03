<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Tarea;
use App\Models\User;

class TareaSeeder extends Seeder
{
    public function run(): void
    {
        $users     = User::where('id', '>', 1)->whereNotNull('area_id')->get();
        $estadoIds = Estado::pluck('id', 'nombre');

        // Pool de estados con variedad (antes quedaban todos hardcodeados en "aprobado").
        $pool = ['borrador', 'borrador', 'borrador', 'validado', 'validado', 'validado',
                 'aprobado', 'aprobado', 'aprobado', 'aprobado', 'aprobado', 'borrado'];

        for ($i = 1; $i <= 15; $i++) {
            $user         = $users->random();
            $estadoNombre = $pool[array_rand($pool)];

            // Avance y fecha correlacionados con el estado: una tarea aprobada ya se
            // completó, una en borrador recién arranca. Un ~20% de las no aprobadas
            // queda "vencida" (fecha pasada con avance incompleto) para poder ver el
            // indicador de vencimiento de la vista (search.blade.php) con datos reales.
            $vencida = $estadoNombre !== 'aprobado' && rand(1, 100) <= 20;

            $porcentajeAvance = match ($estadoNombre) {
                'aprobado' => 100,
                'validado' => rand(60, 99),
                default    => rand(0, 50),
            };

            $fecha = $vencida
                ? now()->subDays(rand(1, 60))
                : now()->addDays(rand(7, 180));

            Tarea::create([
                'nombre'            => 'Tarea ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'descripcion'       => 'Descripción de la tarea ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'fecha'             => $fecha->toDateString(),
                'porcentaje_avance' => $porcentajeAvance,
                'user_id'           => $user->id,
                'area_id'           => $user->area_id,
                'estado_id'         => $estadoIds[$estadoNombre],
            ]);
        }
    }
}
