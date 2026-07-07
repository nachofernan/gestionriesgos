<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_riesgo', function (Blueprint $table) {
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['area_id', 'riesgo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_riesgo');
    }
};
