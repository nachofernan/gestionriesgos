<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_riesgo', function (Blueprint $table) {
            $table->boolean('admite_peis')->default(false)->after('restringe_respuesta');
        });

        // Sólo un riesgo de Corrupción puede tener objetivos PEIS (D-054). Por flag y
        // no por nombre, igual que restringe_respuesta.
        DB::table('tipos_riesgo')->where('nombre', 'Corrupción')->update(['admite_peis' => true]);
    }

    public function down(): void
    {
        Schema::table('tipos_riesgo', function (Blueprint $table) {
            $table->dropColumn('admite_peis');
        });
    }
};
