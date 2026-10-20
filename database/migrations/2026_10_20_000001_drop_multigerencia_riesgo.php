<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se elimina la multigerencia del riesgo (revierte D-003): el riesgo pertenece
 * sólo a su area_id, como el resto de las entidades. No hay datos que migrar:
 * la pivot sólo duplicaba area_id y su gerencia, y no había votos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('validacion_gerencia');
        Schema::dropIfExists('area_riesgo');
    }

    public function down(): void
    {
        Schema::create('area_riesgo', function (Blueprint $table) {
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['area_id', 'riesgo_id']);
        });

        Schema::create('validacion_gerencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actualizacion_id')->constrained('actualizaciones')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->boolean('aprueba');
            $table->timestamps();

            $table->unique(['actualizacion_id', 'area_id']);
        });
    }
};
