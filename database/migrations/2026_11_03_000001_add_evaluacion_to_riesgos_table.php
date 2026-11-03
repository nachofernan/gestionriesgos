<?php

use App\Services\Auditoria\EvaluacionRiesgo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las respuestas vigentes del wizard viven en el riesgo (D-042): el recálculo
 * arranca precargado y la ficha muestra cómo se llegó al número. Los riesgos
 * existentes se completan desde su historial; los que no tienen respuestas
 * que expliquen su número (carga inicial) quedan en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riesgos', function (Blueprint $table) {
            $table->json('evaluacion')->nullable()->after('probabilidad');
        });

        DB::table('riesgos')->select(['id', 'impacto', 'probabilidad'])->orderBy('id')->each(function ($riesgo) {
            $evaluacion = EvaluacionRiesgo::desdeHistorial($riesgo->id, (int) $riesgo->impacto, (int) $riesgo->probabilidad);
            if ($evaluacion !== null) {
                DB::table('riesgos')->where('id', $riesgo->id)->update(['evaluacion' => json_encode($evaluacion)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('riesgos', function (Blueprint $table) {
            $table->dropColumn('evaluacion');
        });
    }
};
