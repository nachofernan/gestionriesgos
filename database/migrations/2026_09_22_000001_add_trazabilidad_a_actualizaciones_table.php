<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quién validó/aprobó/rechazó esta actualización puntual y cuándo. Hasta ahora
        // esto sólo quedaba como un nombre suelto en `data['activated_by']` (sin user_id
        // ni timestamp), o directamente no se registraba (marcarRechazada() ni recibía
        // el usuario).
        Schema::table('actualizaciones', function (Blueprint $table) {
            $table->foreignId('validado_por_id')->nullable()->after('estado_id')->constrained('users');
            $table->timestamp('validado_en')->nullable()->after('validado_por_id');
            $table->foreignId('aprobado_por_id')->nullable()->after('validado_en')->constrained('users');
            $table->timestamp('aprobado_en')->nullable()->after('aprobado_por_id');
            $table->foreignId('rechazado_por_id')->nullable()->after('aprobado_en')->constrained('users');
            $table->timestamp('rechazado_en')->nullable()->after('rechazado_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('actualizaciones', function (Blueprint $table) {
            $table->dropForeign(['validado_por_id']);
            $table->dropForeign(['aprobado_por_id']);
            $table->dropForeign(['rechazado_por_id']);
            $table->dropColumn([
                'validado_por_id', 'validado_en',
                'aprobado_por_id', 'aprobado_en',
                'rechazado_por_id', 'rechazado_en',
            ]);
        });
    }
};
