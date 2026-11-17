<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-051: el control se valora con un cuestionario de 7 preguntas
 * (config/control_preguntas.php) en lugar de los 5 checks provisorios de D-040.
 * Los controles existentes no tienen respuestas y conservan su número
 * (`mitigacion_default`) hasta que alguien conteste el cuestionario.
 *
 * Las propuestas pendientes que cambiaban los checks pierden ese cambio (y el
 * número que lo acompañaba), para que sigan aplicando lo demás; el `diff` que
 * muestra el historial no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controles', function (Blueprint $table) {
            $table->json('evaluacion')->nullable()->after('descripcion');
        });

        $this->limpiarPropuestasPendientes();

        Schema::table('controles', function (Blueprint $table) {
            $table->dropColumn('checks');
        });
    }

    public function down(): void
    {
        Schema::table('controles', function (Blueprint $table) {
            $table->json('checks')->nullable()->after('mitigacion_default');
        });

        Schema::table('controles', function (Blueprint $table) {
            $table->dropColumn('evaluacion');
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
            ->where('actualizable_type', 'like', '%Control')
            ->where('data', 'like', '%checks%')
            ->orderBy('id')
            ->each(function ($fila) {
                $data = json_decode($fila->data, true);
                if (! isset($data['campos']['checks'])) {
                    return;
                }

                unset($data['campos']['checks'], $data['campos']['mitigacion_default'], $data['propagar_mitigacion']);
                DB::table('actualizaciones')->where('id', $fila->id)->update(['data' => json_encode($data)]);
            });
    }
};
