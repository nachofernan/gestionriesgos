<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dimensión del riesgo (D-032): catálogo con sus ejemplos y sus referencias
 * (los principios "Las empresas deben…"), y el riesgo se asocia a una o más.
 * Con dos o más, el riesgo fundamenta por qué (fundamento_dimension).
 * Las filas las crea DimensionSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dimensiones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('ejemplos')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dimension_referencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_id')->constrained('dimensiones')->cascadeOnDelete();
            $table->text('texto');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('dimension_riesgo', function (Blueprint $table) {
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->foreignId('dimension_id')->constrained('dimensiones')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['riesgo_id', 'dimension_id']);
        });

        Schema::table('riesgos', function (Blueprint $table) {
            $table->text('fundamento_dimension')->nullable()->after('fundamento');
        });
    }

    public function down(): void
    {
        Schema::table('riesgos', function (Blueprint $table) {
            $table->dropColumn('fundamento_dimension');
        });
        Schema::dropIfExists('dimension_riesgo');
        Schema::dropIfExists('dimension_referencias');
        Schema::dropIfExists('dimensiones');
    }
};
