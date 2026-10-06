<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revisión de la carga inicial (D-024): una fila por elemento y gerencia
        // firmante. Un riesgo compartido tiene una fila por cada gerencia. Se
        // crea al primer "revisado" o "firmado"; sin fila = pendiente.
        Schema::create('revisiones_iniciales', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisable');
            $table->foreignId('gerencia_id')->constrained('areas');
            $table->foreignId('revisado_por_id')->nullable()->constrained('users');
            $table->timestamp('revisado_en')->nullable();
            $table->foreignId('firmado_por_id')->nullable()->constrained('users');
            $table->timestamp('firmado_en')->nullable();
            $table->text('comentario_revision')->nullable();
            $table->text('comentario_firma')->nullable();
            $table->json('advertencias_aceptadas')->nullable();
            $table->timestamps();

            $table->unique(['revisable_type', 'revisable_id', 'gerencia_id'], 'revisiones_iniciales_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisiones_iniciales');
    }
};
