<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Voto de una gerencia sobre una Actualizacion (propuesta de cambio) de un
        // riesgo compartido entre varias gerencias. La propuesta sólo se escribe
        // como final cuando TODAS las gerencias asociadas votaron a favor; un solo
        // voto en contra la tumba. Con una sola gerencia esta tabla no se usa.
        Schema::create('validacion_gerencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actualizacion_id')->constrained('actualizaciones')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->boolean('aprueba');
            $table->timestamps();

            // Una gerencia vota una sola vez por propuesta (puede cambiar su voto).
            $table->unique(['actualizacion_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validacion_gerencia');
    }
};
