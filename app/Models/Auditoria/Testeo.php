<?php

namespace App\Models\Auditoria;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Testeo de la evidencia de cumplimiento (la "efectividad") de una relación
 * control-riesgo (D-040). Lo carga sólo el auditor y se aplica en el acto, sin
 * validación ni comité: es lo único que nace aprobado. Append-only: un testeo no
 * se edita, el siguiente lo reemplaza. Los informes van a la colección 'adjuntos'.
 */
class Testeo extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $table = 'testeos';

    protected $fillable = [
        'control_id',
        'riesgo_id',
        'user_id',
        'fecha',
        'efectividad_antes',
        'efectividad_despues',
        'comentario',
    ];

    protected $casts = [
        'fecha' => 'date',
        'efectividad_antes' => 'integer',
        'efectividad_despues' => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('adjuntos');
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(Control::class);
    }

    public function riesgo(): BelongsTo
    {
        return $this->belongsTo(Riesgo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registra el testeo y deja la nueva efectividad en la relación, en una
     * transacción. Si el % cambia, el residual del riesgo se mueve, así que su
     * historial recibe una Actualizacion ya aprobada a nombre del auditor. Si no
     * cambia, el testeo queda como confirmación. Quien llama ya autorizó
     * ControlPolicy::testear() y agrega los adjuntos.
     * Tests: un_auditor_registra_un_testeo_y_se_aplica_en_el_acto,
     * un_testeo_en_una_relacion_no_toca_las_otras_del_mismo_control,
     * un_testeo_que_confirma_el_porcentaje_no_deja_cambio_en_el_riesgo.
     */
    public static function registrar(Control $control, Riesgo $riesgo, User $auditor, int $efectividad, Carbon $fecha, string $comentario): self
    {
        $pivot = $control->riesgos()->whereKey($riesgo->id)->firstOrFail()->pivot;
        $antes = (int) $pivot->efectividad;

        return DB::transaction(function () use ($control, $riesgo, $auditor, $efectividad, $fecha, $comentario, $pivot, $antes) {
            $testeo = self::create([
                'control_id' => $control->id,
                'riesgo_id' => $riesgo->id,
                'user_id' => $auditor->id,
                'fecha' => $fecha,
                'efectividad_antes' => $antes,
                'efectividad_despues' => $efectividad,
                'comentario' => $comentario,
            ]);

            if ($efectividad !== $antes) {
                $control->riesgos()->updateExistingPivot($riesgo->id, ['efectividad' => $efectividad]);

                $numero = (int) ($pivot->mitigacion ?? $control->mitigacion_default);
                $riesgo->actualizaciones()->create([
                    'user_id' => $auditor->id,
                    'mensaje' => "Testeo de «{$control->nombre}»: evidencia de cumplimiento {$antes}% → {$efectividad}%",
                    'estado_id' => Estado::aprobado()->id,
                    'aprobado_por_id' => $auditor->id,
                    'aprobado_en' => now(),
                    'data' => [
                        'tipo' => 'cambio',
                        'origen' => 'testeo',
                        'testeo_id' => $testeo->id,
                        'activated_by' => $auditor->name,
                        'diff' => ['relaciones' => ['controles' => ['cambia' => [[
                            'id' => $control->id,
                            'nombre' => $control->nombre,
                            'mitigacion_antes' => Control::aplicarEfectividad($numero, $antes),
                            'mitigacion_despues' => Control::aplicarEfectividad($numero, $efectividad),
                        ]]]]],
                    ],
                ]);
            }

            return $testeo;
        });
    }
}
