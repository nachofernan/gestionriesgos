<?php

namespace App\Models\Auditoria;

use App\Models\Concerns\HasVisibilityScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Control de mitigación aplicable a uno o más Riesgo. `mitigacion_default` sale
 * de los checks del control y es lo que mitiga en cada riesgo, regulado por la
 * evidencia de cumplimiento de cada relación (ControlRiesgo, D-051). Ciclo de vida
 * de estado borrador → validado → aprobado/borrado.
 */
class Control extends Model implements HasMedia
{
    use HasFactory, HasVisibilityScope, InteractsWithMedia, SoftDeletes;

    /**
     * Puntos que validan cuánto mitiga un control (D-040). Son provisorios:
     * hasta que lleguen los definitivos, se cambian acá.
     */
    public const CHECKS = [
        'documentado' => 'Documentado en un procedimiento',
        'responsable_asignado' => 'Responsable asignado',
        'frecuencia_definida' => 'Frecuencia definida',
        'deja_evidencia' => 'Deja evidencia de su ejecución',
        'automatizado_o_segregado' => 'Automatizado o con segregación de funciones',
    ];

    public const PUNTOS_POR_CHECK = 2;

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
        'checks',
        'estado_id',
        'interno',
        'user_id',
        'area_id',
    ];

    protected $casts = [
        'checks' => 'array',
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

        // El número del control sale de sus checks (D-040), por cualquier camino
        // que los guarde: alta, edición del borrador o propuesta aplicada.
        static::saving(function ($control) {
            if ($control->isDirty('checks')) {
                $control->checks = self::normalizarChecks($control->checks);
                $control->mitigacion_default = self::puntajeChecks($control->checks);
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
     * Puntaje de un conjunto de checks (D-040): cada clave válida de CHECKS suma
     * PUNTOS_POR_CHECK. Las claves desconocidas o repetidas no suman. Da el
     * mitigacion_default del control.
     * Test: el_numero_del_control_sale_de_sus_checks.
     */
    public static function puntajeChecks(?array $checks): int
    {
        return count(self::normalizarChecks($checks)) * self::PUNTOS_POR_CHECK;
    }

    /** Sólo las claves válidas, sin repetir y en el orden de CHECKS: así dos selecciones iguales comparan iguales. */
    public static function normalizarChecks(?array $checks): array
    {
        return array_values(array_intersect(array_keys(self::CHECKS), $checks ?? []));
    }

    /** Los checks como texto, para el historial (el diff se muestra tal cual). */
    public static function checksLegibles(?array $checks): string
    {
        $etiquetas = array_map(fn ($clave) => self::CHECKS[$clave], self::normalizarChecks($checks));

        return $etiquetas ? implode(' · ', $etiquetas) : 'Ninguno';
    }
}
