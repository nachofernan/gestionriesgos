<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Método de evaluación ambiental (D-043): el tipo de riesgo dice con qué
 * wizard se calculan impacto y probabilidad, y los riesgos ambientales
 * llevan su caracterización (no numérica, no mueve el valor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_riesgo', function (Blueprint $table) {
            $table->string('metodo_evaluacion')->default('clasica')->after('restringe_respuesta');
        });
        DB::table('tipos_riesgo')->where('nombre', 'Ambiental')->update(['metodo_evaluacion' => 'ambiental']);

        Schema::table('riesgos', function (Blueprint $table) {
            $table->string('temporalidad')->nullable()->after('evaluacion');
            $table->string('condicion')->nullable()->after('temporalidad');
            $table->string('responsabilidad')->nullable()->after('condicion');
        });
    }

    public function down(): void
    {
        Schema::table('riesgos', function (Blueprint $table) {
            $table->dropColumn(['temporalidad', 'condicion', 'responsabilidad']);
        });
        Schema::table('tipos_riesgo', function (Blueprint $table) {
            $table->dropColumn('metodo_evaluacion');
        });
    }
};
