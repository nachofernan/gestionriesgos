<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@admin.com',
            'password' => bcrypt('password'),
        ]);

        $this->call([
            EstadoRiesgoSeeder::class,
            RolSeeder::class,
            TipoRiesgoSeeder::class,
            AreaSeeder::class,
            ObjetivoSeeder::class,
            RiesgoSeeder::class,
            ControlSeeder::class,
            TareaSeeder::class,
            PlanAccionSeeder::class,
        ]);
    }
}
