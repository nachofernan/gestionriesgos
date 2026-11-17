<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-051: el control mitiga lo mismo en cada riesgo; la relación control-riesgo
 * deja de tener número y checks propios y sólo conserva la evidencia de
 * cumplimiento (`efectividad`).
 *
 * Las propuestas pendientes que todavía llevan ese número en sus operaciones se
 * limpian para que sigan aplicando, como en D-050 con los planes (el `diff` que
 * muestra el historial no se toca). Las ya aplicadas no se reescriben.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->limpiarPropuestasPendientes();

        Schema::table('control_riesgo', function (Blueprint $table) {
            $table->dropColumn(['mitigacion', 'checks']);
        });
    }

    public function down(): void
    {
        Schema::table('control_riesgo', function (Blueprint $table) {
            $table->unsignedTinyInteger('mitigacion')->nullable()->after('riesgo_id');
            $table->json('checks')->nullable()->after('mitigacion');
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
            ->where('data', 'like', '%controles%')
            ->orderBy('id')
            ->each(function ($fila) {
                $data = json_decode($fila->data, true);
                $ops = $data['relaciones']['controles'] ?? null;
                if (! is_array($ops)) {
                    return;
                }

                foreach (['sync', 'agregar'] as $op) {
                    if (isset($ops[$op])) {
                        $ops[$op] = array_fill_keys(array_map('strval', array_keys($ops[$op])), []);
                    }
                }
                unset($ops['actualizar']);

                $data['relaciones']['controles'] = $ops;
                DB::table('actualizaciones')->where('id', $fila->id)->update(['data' => json_encode($data)]);
            });
    }
};
