<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mitigación que el plan de acción aplica al riesgo cuando llega al 100% de avance.
 * Se define al asociar el plan (igual que control_riesgo.mitigacion) y sólo descuenta
 * del valor_residual del riesgo si el plan está completo (ver Riesgo::getValorResidualAttribute).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_accion_riesgo', function (Blueprint $table) {
            $table->unsignedTinyInteger('mitigacion')->default(0)->after('riesgo_id');
        });
    }

    public function down(): void
    {
        Schema::table('plan_accion_riesgo', function (Blueprint $table) {
            $table->dropColumn('mitigacion');
        });
    }
};
