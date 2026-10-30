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
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Control de mitigación aplicable a uno o más Riesgo. `mitigacion_default` sale
 * de los checks del control y es el tope de cada relación. La relación
 * (ControlRiesgo) lleva su propio número y su efectividad (D-040). Ciclo de vida
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

    protected $table = 'controles';

    protected $fillable = [
        'nombre',
        'descripcion',
        'mitigacion_default',
        'checks',
        'estado_id',
        'user_id',
        'area_id',
    ];

    protected $casts = [
        'checks' => 'array',
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

    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'control_riesgo')
            ->using(ControlRiesgo::class)
            ->withPivot('mitigacion', 'checks', 'efectividad')
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
     * Regla única del redondeo de la efectividad: hacia abajo, así que sólo al
     * 100% se mitiga el valor entero (D-037). La consume
     * ControlRiesgo::mitigacionEfectiva() y la preview de GestionControles, que
     * trabaja sobre arrays. Test: una_relacion_al_99_no_mitiga_el_valor_entero.
     */
    public static function aplicarEfectividad(int $nominal, int $efectividad): int
    {
        return intdiv($nominal * $efectividad, 100);
    }

    /**
     * Puntaje de un conjunto de checks (D-040): cada clave válida de CHECKS suma
     * PUNTOS_POR_CHECK. Las claves desconocidas o repetidas no suman. Da el
     * mitigacion_default del control y, con tope, el número de cada relación.
     * Test: el_puntaje_suma_dos_por_cada_check_valido.
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

    /**
     * Qué pasa con las relaciones cuando cambia el número del control. Con
     * $propagar ("aplicar también a los riesgos asociados") todas toman el número
     * y los checks del control. Si no, sólo se recortan las que pasan el nuevo
     * tope (D-040). $antes es el número previo. La llaman ControlController::update()
     * y Actualizacion::registrarCambioCampos()/aplicarCambios(), después de guardar.
     * Tests: bajar_el_numero_del_control_recorta_las_relaciones_que_lo_pasan,
     * subir_el_numero_del_control_no_toca_las_relaciones.
     */
    public function trasCambiarDefault(int $antes, User $usuario, bool $propagar): void
    {
        if ((int) $this->mitigacion_default === $antes) {
            return;
        }

        $propagar ? $this->propagarMitigacionDefault($antes, $usuario) : $this->recortarRelacionesAlTope($usuario);
    }

    /**
     * Baja al tope las relaciones cuyo número propio pasa el número del control.
     * Las que no tienen número propio ya heredan el nuevo valor. Cada riesgo
     * afectado recibe una Actualizacion aplicada, como en la propagación.
     */
    private function recortarRelacionesAlTope(User $usuario): void
    {
        $tope = (int) $this->mitigacion_default;

        DB::transaction(function () use ($tope, $usuario) {
            foreach ($this->riesgos()->get() as $riesgo) {
                $anterior = $riesgo->pivot->mitigacion;
                if ($anterior === null || $anterior <= $tope) {
                    continue;
                }

                $this->riesgos()->updateExistingPivot($riesgo->id, ['mitigacion' => $tope]);
                $this->registrarCambioEnRiesgo($riesgo, $anterior, $tope, $usuario, 'recorte al nuevo tope del control', 'tope_control');
            }
        });
    }

    /**
     * Lleva la mitigación por defecto (ya guardada) a todas las asociaciones con
     * riesgos: la opción "aplicar también a los riesgos asociados" al cambiar el
     * default. Pisa todas, incluidos riesgos de otras gerencias, sin pasar por
     * su validación (ver DECISIONES). Cada riesgo cuyo valor cambia
     * recibe una Actualizacion ya aplicada con el mismo diff que una propuesta de
     * mitigación, para que su historial cuente el cambio. $antes es el default
     * previo, que vale para las asociaciones con pivot en null. La llaman
     * Actualizacion::registrarCambioCampos()/aplicarCambios() y ControlController::update().
     * Tests: cambiar_el_numero_tildado_pisa_todas_las_asociaciones_y_baja_el_residual,
     * la_propagacion_deja_una_actualizacion_aplicada_en_cada_riesgo_afectado.
     */
    public function propagarMitigacionDefault(int $antes, User $usuario): void
    {
        $nuevo = (int) $this->mitigacion_default;

        DB::transaction(function () use ($antes, $nuevo, $usuario) {
            foreach ($this->riesgos()->get() as $riesgo) {
                $anterior = (int) ($riesgo->pivot->mitigacion ?? $antes);
                if ($anterior === $nuevo) {
                    continue;
                }

                $this->riesgos()->updateExistingPivot($riesgo->id, ['mitigacion' => $nuevo, 'checks' => $this->checks]);
                $this->registrarCambioEnRiesgo($riesgo, $anterior, $nuevo, $usuario, 'cambio del default del control', 'default_control');
            }
        });
    }

    /** Deja en el historial del riesgo, ya aplicado, el cambio de número de su relación con este control. */
    private function registrarCambioEnRiesgo(Riesgo $riesgo, int $anterior, int $nuevo, User $usuario, string $motivo, string $origen): void
    {
        $riesgo->actualizaciones()->create([
            'user_id' => $usuario->id,
            'mensaje' => "Mitigación de «{$this->nombre}»: {$anterior} → {$nuevo} ({$motivo})",
            'estado_id' => Estado::aprobado()->id,
            'aprobado_por_id' => $usuario->id,
            'aprobado_en' => now(),
            'data' => [
                'tipo' => 'cambio',
                'origen' => $origen,
                'activated_by' => $usuario->name,
                'diff' => ['relaciones' => ['controles' => ['cambia' => [[
                    'id' => $this->id,
                    'nombre' => $this->nombre,
                    'mitigacion_antes' => $anterior,
                    'mitigacion_despues' => $nuevo,
                ]]]]],
            ],
        ]);
    }
}
