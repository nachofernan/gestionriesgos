<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Database\Seeders\EstadoRiesgoSeeder;
use Database\Seeders\TipoRiesgoSeeder;
use Database\Seeders\RiesgoSeeder;
use Database\Seeders\ControlSeeder;
use Database\Seeders\ObjetivoSeeder;
use Database\Seeders\TareaSeeder;
use Database\Seeders\PlanAccionSeeder;

class ReseedAuditoria extends Command
{
    protected $signature   = 'auditoria:reseed';
    protected $description = 'Trunca las tablas de auditoría y vuelve a sembrar los seeders (sin tocar users, sesiones ni áreas)';

    // Orden: primero pivots, luego entidades, luego catálogos
    private array $tables = [
        'actualizaciones',
        'plan_accion_tarea',
        'plan_accion_riesgo',
        'objetivo_riesgo',
        'control_riesgo',
        'tareas',
        'planes_accion',
        'objetivos',
        'controles',
        'riesgos',
        'tipos_riesgo',
        'estados_riesgo',
    ];

    public function handle(): int
    {
        if (! $this->confirm('Esto borrará todos los datos de auditoría (riesgos, controles, objetivos, planes, tareas). ¿Continuar?')) {
            $this->line('Cancelado.');
            return self::SUCCESS;
        }

        $this->info('Truncando tablas...');

        $isSqlite = DB::getDriverName() === 'sqlite';

        if ($isSqlite) {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach ($this->tables as $table) {
            DB::table($table)->truncate();
            $this->line("  ✓ {$table}");
        }

        if ($isSqlite) {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Ejecutando seeders...');

        $seeders = [
            EstadoRiesgoSeeder::class,
            TipoRiesgoSeeder::class,
            RiesgoSeeder::class,
            ControlSeeder::class,
            ObjetivoSeeder::class,
            TareaSeeder::class,
            PlanAccionSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            $this->call('db:seed', ['--class' => $seeder]);
        }

        $this->info('Listo.');
        return self::SUCCESS;
    }
}
