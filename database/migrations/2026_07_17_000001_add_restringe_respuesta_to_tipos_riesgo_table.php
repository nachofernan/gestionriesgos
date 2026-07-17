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
            $table->boolean('restringe_respuesta')->default(false)->after('descripcion');
        });

        // Corrupción no admite compartirse ni aceptarse como respuesta.
        // Se marca por flag y no por nombre para no depender del texto del seeder.
        DB::table('tipos_riesgo')->where('nombre', 'Corrupción')->update(['restringe_respuesta' => true]);
    }

    public function down(): void
    {
        Schema::table('tipos_riesgo', function (Blueprint $table) {
            $table->dropColumn('restringe_respuesta');
        });
    }
};
