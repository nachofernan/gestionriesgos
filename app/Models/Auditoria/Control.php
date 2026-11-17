<?php

namespace App\Models\Auditoria;

use App\Models\Concerns\HasVisibilityScope;
use App\Models\User;
use App\Services\Auditoria\EvaluacionControl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Control de mitigación aplicable a uno o más Riesgo. `mitigacion_default` sale
 * del cuestionario del control (`evaluacion`, EvaluacionControl) y es lo que
 * mitiga en cada riesgo, regulado por la evidencia de cumplimiento de cada
 * relación (ControlRiesgo, D-051). Un control de antes del cuestionario conserva
 * su número hasta que alguien lo conteste. Ciclo de vida de estado borrador →
 * validado → aprobado/borrado.
 */
class Control extends Model implements HasMedia
{
    use HasFactory, HasVisibilityScope, InteractsWithMedia, SoftDeletes;

    /**
     * Tramos de la evidencia de cumplimiento (D-051): desde qué evidencia, qué
     * porcentaje del valor del control aplica. Ordenados de mayor a menor; por
     * debajo de 60 no mitiga nada.
     */
    public const TRAMOS_EVIDENCIA = [99 => 100, 91 => 80, 80 => 60, 70 => 40, 60 => 20];

    protected $table = 'controles';

    protected $fillable = [
        'nombre',
        'descripcion',
        'mitigacion_default',
        'evaluacion',
        'estado_id',
        'interno',
        'user_id',
        'area_id',
    ];

    protected $casts = [
        'evaluacion' => 'array',
        'interno' => 'boolean',
    ];

    protected static function booted()
    {
        // Requiere que exista el estado "borrador" (ver EstadoRiesgoSeeder).
        static::creating(function ($control) {
            if (! $control->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $control->estado_id = $borrador->id;
                }
            }
        });

        // El número del control sale de su cuestionario (D-051), por cualquier
        // camino que lo guarde: borrador, propuesta aplicada o reevaluación del
        // auditor. Sin cuestionario se conserva el número que traía.
        // Test: un_control_expresion_de_deseo_muestra_puntaje_y_mitiga_cero.
        static::saving(function ($control) {
            if ($control->isDirty('evaluacion') && $control->evaluacion !== null) {
                $control->mitigacion_default = EvaluacionControl::mitigacion($control->evaluacion);
            }
        });
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function actualizaciones(): MorphMany
    {
        return $this->morphMany(Actualizacion::class, 'actualizable')->latest('created_at');
    }

    // Firmas de la revisión de la carga inicial (D-024), una por gerencia.
    public function revisionesIniciales(): MorphMany
    {
        return $this->morphMany(RevisionInicial::class, 'revisable');
    }

    /**
     * Motivo por el que el control no se puede eliminar (null si se puede, D-047):
     * tiene que estar aprobado y no mitigar ningún riesgo vigente salvo
     * $juntoConRiesgoId, el riesgo con el que se propuso eliminarlo. Se consulta al
     * ofrecerlo (Riesgo::dependientesEliminables()) y otra vez al aplicar, porque
     * en el medio pudo asociarse a otro riesgo.
     * Test: un_control_asociado_despues_a_otro_riesgo_no_se_elimina_al_aprobar.
     */
    public function motivoBloqueoEliminacion(?int $juntoConRiesgoId = null): ?string
    {
        if ($this->estado?->nombre !== 'aprobado') {
            return 'No está aprobado.';
        }

        $otros = $this->riesgos()
            ->where('riesgos.id', '!=', $juntoConRiesgoId ?? 0)
            ->where('riesgos.estado_id', '!=', Estado::borrado()->id)
            ->exists();

        return $otros ? 'También mitiga otros riesgos.' : null;
    }

    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'control_riesgo')
            ->using(ControlRiesgo::class)
            ->withPivot('efectividad')
            ->withTimestamps();
    }

    /**
     * Regla única de si el control descuenta del residual de sus riesgos: sólo si
     * está aprobado. La consumen Riesgo::getMitigacionControlesAttribute() y las
     * pantallas que muestran o previsualizan el descuento (requiere `estado` cargado).
     * Tests: un_control_no_aprobado_no_baja_el_valor_residual,
     * la_mitigacion_del_plan_y_la_de_los_controles_aprobados_se_suman.
     */
    public function mitiga(): bool
    {
        return $this->estado?->nombre === 'aprobado';
    }

    /**
     * Si el control descuenta en la proyección de un borrador interno (D-044): los
     * aprobados, como siempre, más los borradores internos; un borrador sin flag o
     * un validado no. Sólo la consume Riesgo::getMitigacionControlesProyectadaAttribute().
     * Tests: el_residual_proyectado_suma_lo_aprobado_y_lo_interno,
     * un_borrador_sin_flag_o_un_validado_no_proyectan.
     */
    public function mitigaProyectado(): bool
    {
        return $this->mitiga() || ($this->interno && $this->estado?->nombre === 'borrador');
    }

    /**
     * Regla única de la evidencia de cumplimiento (D-051): la evidencia de una
     * relación no regula el valor en forma lineal sino por tramos (ver TRAMOS_EVIDENCIA),
     * y la proporción se redondea hacia abajo (D-037), así que sólo con 99 o 100
     * se mitiga el valor entero. La consume ControlRiesgo::mitigacionEfectiva(),
     * la preview de GestionControles y el testeo.
     * Tests: una_evidencia_menor_a_60_no_mitiga_nada,
     * cada_tramo_de_evidencia_mitiga_su_porcentaje_hacia_abajo,
     * una_evidencia_de_98_no_mitiga_el_valor_entero.
     */
    public static function aplicarEvidencia(int $valor, int $evidencia): int
    {
        return intdiv($valor * self::porcentajeDeEvidencia($evidencia), 100);
    }

    /** Qué porcentaje del valor aplica una evidencia: el del primer tramo que la alcanza. */
    public static function porcentajeDeEvidencia(int $evidencia): int
    {
        foreach (self::TRAMOS_EVIDENCIA as $desde => $porcentaje) {
            if ($evidencia >= $desde) {
                return $porcentaje;
            }
        }

        return 0;
    }

    /**
     * Puntaje del cuestionario (de 1 a 10), o el número que traía el control si
     * todavía no lo contestaron. Puede no coincidir con mitigacion_default: un
     * control que es expresión de deseo suma, pero mitiga 0.
     */
    public function getPuntajeAttribute(): int
    {
        return $this->evaluacion === null
            ? (int) $this->mitigacion_default
            : EvaluacionControl::puntaje($this->evaluacion);
    }

    /**
     * Clasificación cualitativa del control (D-051): deficiente, moderado o
     * suficiente según su puntaje (EvaluacionControl::clasificacion()). Uno que
     * es expresión de deseo es deficiente. Sin cuestionario se clasifica por el
     * número que traía.
     * Test: la_clasificacion_del_control_sigue_sus_umbrales.
     *
     * @return array{etiqueta:string, color:string}
     */
    public function clasificacion(): array
    {
        return EvaluacionControl::clasificacion(
            $this->puntaje,
            $this->evaluacion === null || EvaluacionControl::operativo($this->evaluacion),
        );
    }

    /**
     * Motivos por los que el control no se valida ni se aprueba (vacío si se
     * puede): sin el cuestionario contestado no hay valor que respalde lo que
     * mitiga. Lo consultan ControlController::validar()/aprobar() y
     * ValidacionMasivaService (también en la cascada de un riesgo).
     * Tests: un_control_sin_cuestionario_no_se_valida,
     * la_validacion_masiva_no_valida_un_control_sin_cuestionario.
     *
     * @return list<string>
     */
    public function motivosBloqueoValidacion(): array
    {
        return $this->evaluacion === null
            ? ['Falta contestar el cuestionario del control (card «Valor del control»).']
            : [];
    }

    /**
     * Motivo por el que $user no puede contestar ahora el cuestionario por el
     * circuito del área (null si puede). El comité no valora: aprueba lo que le
     * proponen. El auditor no propone: reevalúa en el acto un control aprobado
     * (ControlPolicy::reevaluar). Una propuesta pendiente sobre el cuestionario
     * frena otra. Quien llama ya autorizó 'proponer'.
     * Tests: el_comite_no_valora_controles, una_propuesta_pendiente_frena_otra_valoracion.
     */
    public function motivoBloqueoValoracion(User $user): ?string
    {
        return match (true) {
            $user->esComite() => 'El comité no valora controles: aprueba la valoración que le proponen.',
            $user->esAuditor() => 'El auditor reevalúa el control una vez aprobado.',
            $this->valoracionPendiente() => 'Ya hay una propuesta pendiente sobre el cuestionario.',
            default => null,
        };
    }

    /** Si hay una propuesta pendiente que cambia el cuestionario. */
    public function valoracionPendiente(): bool
    {
        return $this->actualizaciones()->propuestasPendientes('campos')->get()
            ->contains(fn (Actualizacion $a) => array_key_exists('evaluacion', $a->data['campos'] ?? []));
    }

    /**
     * Registra el cuestionario $evaluacion (ya validado con
     * EvaluacionControl::reglas()) por el circuito del área. En borrador se
     * aplica en el acto y deja rastro en el historial; fuera de borrador es un
     * cambio de campo como cualquier otro (Actualizacion::registrarCambioCampos()):
     * sobre un control aprobado queda como propuesta y no mueve el residual hasta
     * que la aprueba el comité. Devuelve null si no cambió nada. Quien llama ya
     * autorizó y consultó motivoBloqueoValoracion().
     * Tests: en_borrador_el_cuestionario_se_aplica_directo,
     * sobre_un_control_aprobado_el_area_propone_y_no_mueve_el_residual_hasta_aprobar.
     */
    public function registrarValoracion(User $user, array $evaluacion): ?Actualizacion
    {
        if ($evaluacion == $this->evaluacion) {
            return null;
        }

        $mensaje = 'Cuestionario del control';

        if ($this->estado?->nombre !== 'borrador') {
            return Actualizacion::registrarCambioCampos($this, $user, $mensaje, ['evaluacion' => $evaluacion]);
        }

        return DB::transaction(function () use ($user, $evaluacion, $mensaje) {
            $antes = $this->evaluacion;
            $this->update(['evaluacion' => $evaluacion]);

            return $this->actualizaciones()->create([
                'user_id' => $user->id,
                'mensaje' => $mensaje,
                'estado_id' => Estado::borrador()->id,
                'data' => ['tipo' => 'edicion', 'diff' => ['campos' => ['evaluacion' => ['antes' => $antes, 'despues' => $evaluacion]]]],
            ]);
        });
    }
}
