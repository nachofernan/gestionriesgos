<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('area_riesgo', function (Blueprint $table) {
            // Marca si la gerencia asociada es ajena a la de origen del riesgo:
            // cuando lo es, toda su gente (gerente + empleados) gana acceso de
            // gestión, no sólo quien la tenga como área ancestro-o-igual. La
            // gerencia propia del riesgo siempre queda en false.
            $table->boolean('gerencia_ajena')->default(false)->after('riesgo_id');
        });
    }

    public function down(): void
    {
        Schema::table('area_riesgo', function (Blueprint $table) {
            $table->dropColumn('gerencia_ajena');
        });
    }
};
