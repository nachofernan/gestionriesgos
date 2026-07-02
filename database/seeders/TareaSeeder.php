<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Auditoria\Tarea;
use App\Models\User;

class TareaSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)->whereNotNull('area_id')->get();

        for ($i = 1; $i <= 15; $i++) {
            $user = $users->random();
            Tarea::create([
                'nombre'            => 'Tarea ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'descripcion'       => 'Descripción de la tarea ' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'fecha'             => now()->addDays(rand(7, 180))->toDateString(),
                'porcentaje_avance' => rand(0, 100),
                'user_id'           => $user->id,
                'area_id'           => $user->area_id,
                'estado_id'          => 3,
            ]);
        }
    }
}
