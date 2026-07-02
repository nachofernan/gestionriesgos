<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------
        // ÁREAS
        // -------------------------------------------------------
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('area_padre_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
        });

        // -------------------------------------------------------
        // ESTADOS DE RIESGO
        // -------------------------------------------------------
        Schema::create('estados_riesgo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // borrador, validado, activo, borrado
            $table->string('color')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // -------------------------------------------------------
        // TIPOS DE RIESGO
        // -------------------------------------------------------
        Schema::create('tipos_riesgo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // -------------------------------------------------------
        // RIESGOS
        // -------------------------------------------------------
        Schema::create('riesgos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable()->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedTinyInteger('impacto')->default(0);      // 0-10
            $table->unsignedTinyInteger('probabilidad')->default(0); // 0-10
            $table->boolean('mayor_criticidad')->default(false); // solo cuando impacto+probabilidad >= 14
            $table->foreignId('tipo_riesgo_id')->constrained('tipos_riesgo');
            $table->foreignId('estado_riesgo_id')->constrained('estados_riesgo');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('area_id')->nullable();       // sin FK por ahora
            $table->timestamps();
            $table->softDeletes();
        });

        // -------------------------------------------------------
        // CONTROLES
        // -------------------------------------------------------
        Schema::create('controles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedTinyInteger('mitigacion_default')->default(1); // 1-10
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // -------------------------------------------------------
        // PIVOT: control_riesgo
        // -------------------------------------------------------
        Schema::create('control_riesgo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained('controles')->cascadeOnDelete();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->unsignedTinyInteger('mitigacion')->nullable(); // null = usa mitigacion_default del control
            $table->timestamps();

            $table->unique(['control_id', 'riesgo_id']);
        });

        // -------------------------------------------------------
        // OBJETIVOS
        // -------------------------------------------------------
        Schema::create('objetivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->date('fecha_objetivo')->nullable();
            $table->boolean('estrategico')->default(false);
            $table->boolean('anticorrupcion')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // -------------------------------------------------------
        // PIVOT: objetivo_riesgo
        // -------------------------------------------------------
        Schema::create('objetivo_riesgo', function (Blueprint $table) {
            $table->foreignId('objetivo_id')->constrained('objetivos')->cascadeOnDelete();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['objetivo_id', 'riesgo_id']);
        });

        // -------------------------------------------------------
        // PLANES DE ACCIÓN
        // -------------------------------------------------------
        Schema::create('planes_accion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // -------------------------------------------------------
        // PIVOT: plan_accion_riesgo
        // -------------------------------------------------------
        Schema::create('plan_accion_riesgo', function (Blueprint $table) {
            $table->foreignId('plan_accion_id')->constrained('planes_accion')->cascadeOnDelete();
            $table->foreignId('riesgo_id')->constrained('riesgos')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['plan_accion_id', 'riesgo_id']);
        });

        // -------------------------------------------------------
        // TAREAS
        // -------------------------------------------------------
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->date('fecha')->nullable();
            $table->unsignedTinyInteger('porcentaje_avance')->default(0); // 0-100
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // -------------------------------------------------------
        // PIVOT: plan_accion_tarea
        // -------------------------------------------------------
        Schema::create('plan_accion_tarea', function (Blueprint $table) {
            $table->foreignId('plan_accion_id')->constrained('planes_accion')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['plan_accion_id', 'tarea_id']);
        });

        // -------------------------------------------------------
        // ACTUALIZACIONES DE TAREA
        // -------------------------------------------------------
        Schema::create('actualizaciones_tarea', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->text('mensaje')->nullable();
            $table->unsignedTinyInteger('porcentaje_avance');
            $table->timestamps();
            $table->softDeletes();
        });

        // FK de area_id en tablas de entidades (ya tienen la columna nullable)
        foreach (['riesgos', 'controles', 'objetivos', 'planes_accion', 'tareas'] as $entidad) {
            Schema::table($entidad, function (Blueprint $table) {
                $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('actualizaciones_tarea');
        Schema::dropIfExists('plan_accion_tarea');
        Schema::dropIfExists('plan_accion_riesgo');
        Schema::dropIfExists('tareas');
        Schema::dropIfExists('planes_accion');
        Schema::dropIfExists('objetivo_riesgo');
        Schema::dropIfExists('objetivos');
        Schema::dropIfExists('control_riesgo');
        Schema::dropIfExists('controles');
        Schema::dropIfExists('riesgos');
        Schema::dropIfExists('tipos_riesgo');
        Schema::dropIfExists('estados_riesgo');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
        });
        Schema::dropIfExists('areas');
    }
};