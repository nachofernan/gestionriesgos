<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrador interno (D-044): un borrador con flag es gestión propia del área, no
 * entra a Pendientes ni se valida, y proyecta su mitigación. Sólo existe en
 * borrador; lo existente queda sin flag.
 */
return new class extends Migration
{
    private const TABLAS = ['riesgos', 'controles', 'objetivos', 'planes_accion', 'tareas'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->boolean('interno')->default(false)->after('estado_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('interno');
            });
        }
    }
};
