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
 * Plan de Acción con código correlativo (ver PlanAccionController::generarCodigo()),
 * asociado a uno o más Riesgo y a las Tarea que lo ejecutan (ambas many-to-many).
 * Ciclo de vida de estado borrador → validado → aprobado/borrado.
 */
class PlanAccion extends Model implements HasMedia
{
    use HasFactory, HasVisibilityScope, InteractsWithMedia, SoftDeletes;

    protected $table = 'planes_accion';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'estado_id',
        'user_id',
        'area_id',
    ];

    protected static function booted()
    {
        // Requiere que exista el estado "borrador" (ver EstadoRiesgoSeeder).
        static::creating(function ($plan) {
            if (! $plan->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $plan->estado_id = $borrador->id;
                }
            }
        });
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function actualizaciones(): MorphMany
    {
        return $this->morphMany(Actualizacion::class, 'actualizable')->latest('created_at');
    }

    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'plan_accion_riesgo')
            ->withPivot('mitigacion')
            ->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'plan_accion_tarea')
            ->withTimestamps();
    }

    // -------------------------------------------------------
    // Accessors
    // -------------------------------------------------------

    /**
     * Avance del plan como promedio del `porcentaje_avance` de sus tareas en estado
     * "aprobado" (0-100), o null si no tiene ninguna tarea aprobada. Las tareas en
     * borrador/validado son trabajo real pendiente pero no cuentan para el avance, y
     * las de estado "borrado" quedan fuera por completo. Un plan al 100% es el que
     * aplica su mitigación al valor_residual del riesgo (ver
     * Riesgo::getValorResidualAttribute). Consumir con `tareas.estado` eager-loaded.
     */
    public function getAvanceAttribute(): ?int
    {
        $aprobadas = $this->tareas->filter(fn ($tarea) => $tarea->estado?->nombre === 'aprobado');

        if ($aprobadas->isEmpty()) {
            return null;
        }

        return (int) round($aprobadas->avg('porcentaje_avance'));
    }

    public function estaCompleto(): bool
    {
        return $this->avance === 100;
    }

    /**
     * Fecha de vencimiento del plan: la más próxima entre sus tareas todavía
     * pendientes (`porcentaje_avance` < 100), vigentes (no "borrado"). Una vez
     * que una tarea llega al 100% deja de contar, aunque su fecha haya quedado
     * en el pasado. null si no hay ninguna pendiente con fecha. Consumir con
     * `tareas.estado` eager-loaded.
     */
    public function getVencimientoAttribute(): ?\Illuminate\Support\Carbon
    {
        $fecha = $this->tareas
            ->reject(fn ($tarea) => $tarea->estado?->nombre === 'borrado')
            ->where('porcentaje_avance', '<', 100)
            ->whereNotNull('fecha')
            ->min('fecha');

        return $fecha ? \Illuminate\Support\Carbon::parse($fecha) : null;
    }

    /**
     * Un plan está vencido cuando su tarea pendiente más próxima ya pasó de
     * fecha (ver getVencimientoAttribute). Cubierto por
     * un_plan_esta_vencido_si_alguna_tarea_pendiente_paso_su_fecha.
     */
    public function getEstaVencidoAttribute(): bool
    {
        return $this->vencimiento !== null && $this->vencimiento->lt(now()->startOfDay());
    }
}
