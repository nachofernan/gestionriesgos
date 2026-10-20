<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naturaleza del riesgo (D-032): catálogo con sus ejemplos y sus referencias
 * (los principios "Las empresas deben…"), y el riesgo se asocia a una o más.
 * Con dos o más, el riesgo fundamenta por qué (fundamento_naturaleza).
 * Las filas las crea NaturalezaSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('naturalezas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('ejemplos')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('naturaleza_referencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('naturaleza_id')->constrained('naturalezas')->cascadeOnDelete();
            $table->text('texto');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('naturaleza_riesgo', function (Blueprint $table) {
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->foreignId('naturaleza_id')->constrained('naturalezas')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['riesgo_id', 'naturaleza_id']);
        });

        Schema::table('riesgos', function (Blueprint $table) {
            $table->text('fundamento_naturaleza')->nullable()->after('fundamento');
        });
    }

    public function down(): void
    {
        Schema::table('riesgos', function (Blueprint $table) {
            $table->dropColumn('fundamento_naturaleza');
        });
        Schema::dropIfExists('naturaleza_riesgo');
        Schema::dropIfExists('naturaleza_referencias');
        Schema::dropIfExists('naturalezas');
    }
};
