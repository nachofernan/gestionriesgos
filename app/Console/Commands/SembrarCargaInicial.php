<?php

namespace App\Console\Commands;

use Database\Seeders\CargaInicial\CargaInicialSeeder;
use Illuminate\Console\Command;

/**
 * Atajo para reconstruir la base desde cero con la carga inicial real de
 * auditoría (docs/datos/*.csv → CargaInicialSeeder, ver docs/reconstruccion.md
 * y docs/reconstruccion-2.md). Equivale a correr a mano:
 *   php artisan migrate:fresh
 *   php artisan db:seed --class="Database\Seeders\CargaInicial\CargaInicialSeeder"
 * migrate:fresh borra todas las tablas, por eso pide confirmación salvo --force.
 */
class SembrarCargaInicial extends Command
{
    protected $signature = 'auditoria:carga-inicial {--force : No pedir confirmación}';

    protected $description = 'Recrea la base (migrate:fresh) y siembra la carga inicial real de auditoría';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'Esto va a BORRAR todas las tablas de la base actual y sembrar la carga inicial real de auditoría. ¿Continuar?'
        )) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        $this->call('migrate:fresh');
        $this->call('db:seed', ['--class' => CargaInicialSeeder::class]);

        $this->info('Base recreada y carga inicial sembrada.');

        return self::SUCCESS;
    }
}
