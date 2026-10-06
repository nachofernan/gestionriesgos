<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inconsistencias que al firmar tenían su arreglo en una propuesta validada
        // que esperaba al comité (D-025). Si el comité la rechaza, la firma queda.
        Schema::table('revisiones_iniciales', function (Blueprint $table) {
            $table->json('arreglos_pendientes')->nullable()->after('advertencias_aceptadas');
        });
    }

    public function down(): void
    {
        Schema::table('revisiones_iniciales', function (Blueprint $table) {
            $table->dropColumn('arreglos_pendientes');
        });
    }
};
