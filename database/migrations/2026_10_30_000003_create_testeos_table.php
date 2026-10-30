<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Testeos de la evidencia de cumplimiento de una relación control-riesgo (D-040).
 * Append-only: cada testeo es una fila nueva; la efectividad vigente vive en
 * control_riesgo.efectividad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testeos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained('controles');
            $table->foreignId('riesgo_id')->constrained('riesgos');
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->unsignedTinyInteger('efectividad_antes');
            $table->unsignedTinyInteger('efectividad_despues');
            $table->text('comentario');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['control_id', 'riesgo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testeos');
    }
};
