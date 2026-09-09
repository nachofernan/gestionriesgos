<?php

namespace Database\Seeders\CargaInicial;

use App\Models\Auditoria\Estado;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Database\Seeder;

/**
 * Orquesta la primera carga real de datos de auditoría, a partir del Excel
 * exportado a docs/datos/*.csv (ver docs/reconstruccion.md para el análisis
 * completo de cada CSV y las decisiones de negocio que rigen la carga). Corre
 * aparte de DatabaseSeeder: no toca los seeders "de juguete" existentes, que
 * siguen sirviendo para tests.
 *
 * Uso: php artisan db:seed --class="Database\Seeders\CargaInicial\CargaInicialSeeder"
 * Pensado para correr una sola vez sobre una base recién migrada.
 */
class CargaInicialSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EstadoRiesgoSeeder::class);
        MapeoCargaInicial::$estadoIdPorNombre = Estado::pluck('id', 'nombre')->all();

        $this->call([
            TipoRiesgoCargaInicialSeeder::class,
            AreaCargaInicialSeeder::class,
            RiesgoCargaInicialSeeder::class,
            PeisItemCargaInicialSeeder::class,
            ObjetivoCargaInicialSeeder::class,
            ControlCargaInicialSeeder::class,
            PlanAccionCargaInicialSeeder::class,
            TareaCargaInicialSeeder::class,
            PlanAccionTareaCargaInicialSeeder::class,
        ]);
    }
}
