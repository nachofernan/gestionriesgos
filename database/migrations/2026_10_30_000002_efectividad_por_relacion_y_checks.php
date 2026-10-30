<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D-040: la mitigación del control sale de checks y la efectividad pasa del
 * control a cada relación control-riesgo. Los datos se re-siembran, así que no
 * se copia la efectividad vieja a las relaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controles', function (Blueprint $table) {
            $table->json('checks')->nullable()->after('mitigacion_default');
            $table->dropColumn('efectividad');
        });

        Schema::table('control_riesgo', function (Blueprint $table) {
            $table->json('checks')->nullable()->after('mitigacion');
            $table->unsignedTinyInteger('efectividad')->default(100)->after('checks');
        });
    }

    public function down(): void
    {
        Schema::table('control_riesgo', function (Blueprint $table) {
            $table->dropColumn(['checks', 'efectividad']);
        });

        Schema::table('controles', function (Blueprint $table) {
            $table->dropColumn('checks');
            $table->unsignedTinyInteger('efectividad')->default(100)->after('mitigacion_default');
        });
    }
};
