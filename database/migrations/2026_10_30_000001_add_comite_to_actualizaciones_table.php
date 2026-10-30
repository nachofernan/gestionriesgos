<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comité que dictaminó una aprobación (D-039). Opcional: lo cargado antes queda en null.
 * `comite_de_id` marca una entrada que completa o corrige el comité de otra aprobación,
 * sin reescribirla (historial append-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actualizaciones', function (Blueprint $table) {
            $table->string('comite_numero', 20)->nullable()->after('aprobado_en');
            $table->date('comite_fecha')->nullable()->after('comite_numero');
            $table->foreignId('comite_de_id')->nullable()->after('comite_fecha')->constrained('actualizaciones');
        });
    }

    public function down(): void
    {
        Schema::table('actualizaciones', function (Blueprint $table) {
            $table->dropForeign(['comite_de_id']);
            $table->dropColumn(['comite_numero', 'comite_fecha', 'comite_de_id']);
        });
    }
};
