<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Renombrar tabla estados_riesgo → estados
        Schema::rename('estados_riesgo', 'estados');

        // 2. Renombrar FK en riesgos: estado_riesgo_id → estado_id
        Schema::table('riesgos', function (Blueprint $table) {
            $table->dropForeign(['estado_riesgo_id']);
            $table->renameColumn('estado_riesgo_id', 'estado_id');
        });
        Schema::table('riesgos', function (Blueprint $table) {
            $table->foreign('estado_id')->references('id')->on('estados');
        });

        // 3. Agregar estado_id a controles, objetivos, planes_accion, tareas
        foreach (['controles', 'objetivos', 'planes_accion', 'tareas'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('estado_id')->nullable()->constrained('estados');
            });
        }

        // Asignar estado borrador a filas existentes
        $borrador = DB::table('estados')->where('nombre', 'borrador')->first();
        if ($borrador) {
            foreach (['controles', 'objetivos', 'planes_accion', 'tareas'] as $tabla) {
                DB::table($tabla)->update(['estado_id' => $borrador->id]);
            }
        }

        // 4. Agregar estado_id a actualizaciones (nullable: registros históricos sin estado)
        Schema::table('actualizaciones', function (Blueprint $table) {
            $table->foreignId('estado_id')->nullable()->constrained('estados');
        });

        // 5. Agregar rol a users
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol')->default('empleado')->after('area_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol');
        });

        Schema::table('actualizaciones', function (Blueprint $table) {
            $table->dropForeign(['estado_id']);
            $table->dropColumn('estado_id');
        });

        foreach (['tareas', 'planes_accion', 'objetivos', 'controles'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropForeign(['estado_id']);
                $table->dropColumn('estado_id');
            });
        }

        Schema::table('riesgos', function (Blueprint $table) {
            $table->dropForeign(['estado_id']);
            $table->renameColumn('estado_id', 'estado_riesgo_id');
        });

        Schema::rename('estados', 'estados_riesgo');

        Schema::table('riesgos', function (Blueprint $table) {
            $table->foreign('estado_riesgo_id')->references('id')->on('estados_riesgo');
        });
    }
};
