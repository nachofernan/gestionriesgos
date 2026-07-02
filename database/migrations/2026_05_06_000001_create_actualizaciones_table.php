<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actualizaciones', function (Blueprint $table) {
            $table->id();
            $table->morphs('actualizable');         // actualizable_type, actualizable_id
            $table->foreignId('user_id')->constrained('users');
            $table->text('mensaje');
            $table->json('data')->nullable();       // {porcentaje: 75} para tareas, null para controles, etc.
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::drop('actualizaciones_tarea');
    }

    public function down(): void
    {
        Schema::create('actualizaciones_tarea', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->text('mensaje')->nullable();
            $table->unsignedTinyInteger('porcentaje_avance');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::dropIfExists('actualizaciones');
    }
};
