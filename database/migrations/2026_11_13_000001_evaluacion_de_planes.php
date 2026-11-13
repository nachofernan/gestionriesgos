<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-050: el plan se valora con un cuestionario (estimado al diseñarlo, real al
 * terminarlo) que reduce impacto y probabilidad por separado. La mitigación
 * manual de cada relación plan-riesgo desaparece.
 *
 * Las propuestas pendientes que todavía llevan ese número en sus operaciones se
 * limpian para que sigan aplicando (el `diff` que muestra el historial no se
 * toca). Las ya aplicadas no se reescriben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_accion', function (Blueprint $table) {
            $table->json('evaluacion_estimada')->nullable()->after('descripcion');
            $table->json('evaluacion_final')->nullable()->after('evaluacion_estimada');
        });

        $this->limpiarPropuestasPendientes();

        Schema::table('plan_accion_riesgo', function (Blueprint $table) {
            $table->dropColumn('mitigacion');
        });
    }

    public function down(): void
    {
        Schema::table('plan_accion_riesgo', function (Blueprint $table) {
            $table->unsignedTinyInteger('mitigacion')->default(0)->after('riesgo_id');
        });

        Schema::table('planes_accion', function (Blueprint $table) {
            $table->dropColumn(['evaluacion_estimada', 'evaluacion_final']);
        });
    }

    private function limpiarPropuestasPendientes(): void
    {
        $pendientes = DB::table('estados')->whereIn('nombre', ['borrador', 'validado'])->pluck('id');
        if ($pendientes->isEmpty()) {
            return;
        }

        DB::table('actualizaciones')
            ->whereIn('estado_id', $pendientes)
            ->where('data', 'like', '%planesAccion%')
            ->orderBy('id')
            ->each(function ($fila) {
                $data = json_decode($fila->data, true);
                $ops = $data['relaciones']['planesAccion'] ?? null;
                if (! is_array($ops)) {
                    return;
                }

                foreach (['sync', 'agregar'] as $op) {
                    if (isset($ops[$op])) {
                        $ops[$op] = array_fill_keys(array_map('strval', array_keys($ops[$op])), []);
                    }
                }
                unset($ops['actualizar']);

                $data['relaciones']['planesAccion'] = $ops;
                DB::table('actualizaciones')->where('id', $fila->id)->update(['data' => json_encode($data)]);
            });
    }
};
