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
     * Avance del plan como promedio del `porcentaje_avance` de sus tareas (0-100),
     * o null si no tiene tareas. Un plan al 100% es el que aplica su mitigación al
     * valor_residual del riesgo (ver Riesgo::getValorResidualAttribute).
     */
    public function getAvanceAttribute(): ?int
    {
        if ($this->tareas->isEmpty()) {
            return null;
        }

        return (int) round($this->tareas->avg('porcentaje_avance'));
    }

    public function estaCompleto(): bool
    {
        return $this->avance === 100;
    }
}
