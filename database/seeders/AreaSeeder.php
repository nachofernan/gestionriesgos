<?php

namespace Database\Seeders;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $comite = Area::create(['nombre' => 'Comité de Riesgo',        'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]); // Lucia

        $gerAdmin = Area::create(['nombre' => 'Gerencia Administración',  'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]); // Canela
        $gerProd = Area::create(['nombre' => 'Gerencia Producción',      'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]); // Grassi

        $coordSist = Area::create(['nombre' => 'Coordinación Sistemas',    'area_padre_id' => $gerAdmin->id]); // Diego
        $coordFin = Area::create(['nombre' => 'Coordinación Finanzas',    'area_padre_id' => $gerAdmin->id]); // Seba

        $desarrollo = Area::create(['nombre' => 'Desarrollo',              'area_padre_id' => $coordSist->id]); // Nacho
        $informatica = Area::create(['nombre' => 'Informática',             'area_padre_id' => $coordSist->id]); // Tito

        $compras = Area::create(['nombre' => 'Compras',                 'area_padre_id' => $coordFin->id]); // Laza
        $cuentasPagar = Area::create(['nombre' => 'Cuentas a Pagar',         'area_padre_id' => $coordFin->id]); // Silvina

        $villaGesell = Area::create(['nombre' => 'Central Villa Gesell',    'area_padre_id' => $gerProd->id]); // Nocetti
        $marDelPlata = Area::create(['nombre' => 'Central Mar del Plata',   'area_padre_id' => $gerProd->id]); // Zanotti

        $areas = [
            ['area' => $comite, 'responsable' => 'Lucia', 'rol' => 'comite'],
            ['area' => $gerAdmin, 'responsable' => 'Canela', 'rol' => 'gerente'],
            ['area' => $gerProd, 'responsable' => 'Grassi', 'rol' => 'gerente'],
            ['area' => $coordSist, 'responsable' => 'Diego', 'rol' => 'empleado'],
            ['area' => $coordFin, 'responsable' => 'Seba', 'rol' => 'empleado'],
            ['area' => $desarrollo, 'responsable' => 'Nacho', 'rol' => 'empleado'],
            ['area' => $informatica, 'responsable' => 'Tito', 'rol' => 'empleado'],
            ['area' => $compras, 'responsable' => 'Laza', 'rol' => 'empleado'],
            ['area' => $cuentasPagar, 'responsable' => 'Silvina', 'rol' => 'empleado'],
            ['area' => $villaGesell, 'responsable' => 'Nocetti', 'rol' => 'empleado'],
            ['area' => $marDelPlata, 'responsable' => 'Zanotti', 'rol' => 'empleado'],
        ];

        foreach ($areas as $i => $area) {
            $n = $i + 1;
            User::create([
                'name' => "{$area['responsable']}",
                'email' => strtolower($area['responsable']).'@example.com',
                'password' => Hash::make('password'),
                'area_id' => $area['area']->id,
                'rol' => $area['rol'],
            ]);
        }
    }
}
