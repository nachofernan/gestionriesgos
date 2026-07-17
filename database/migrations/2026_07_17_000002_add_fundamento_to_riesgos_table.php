<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riesgos', function (Blueprint $table) {
            // Nullable en el esquema aunque sea obligatorio para las respuestas
            // que lo exigen: `respuesta` también es nullable, y un riesgo puede
            // crearse sin definirla todavía.
            $table->text('fundamento')->nullable()->after('respuesta');
        });
    }

    public function down(): void
    {
        Schema::table('riesgos', function (Blueprint $table) {
            $table->dropColumn('fundamento');
        });
    }
};
