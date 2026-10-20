<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sedes de la empresa (D-029): catálogo, y por ahora sólo el riesgo se asocia a
 * una, varias o todas. Las filas las crea SedeSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('riesgo_sede', function (Blueprint $table) {
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['riesgo_id', 'sede_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riesgo_sede');
        Schema::dropIfExists('sedes');
    }
};
