<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        // Primer usuario existente → gerente
        $primero = User::orderBy('id')->first();
        if ($primero && !$primero->area_id) {
            $primero->update(['rol' => 'gerente']);
        }
    }
}
