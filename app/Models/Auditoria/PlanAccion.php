<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\User;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Concerns\HasVisibilityScope;

/**
 * Plan de Acción con código correlativo (ver PlanAccionController::generarCodigo()),
 * asociado a uno o más Riesgo y a las Tarea que lo ejecutan (ambas many-to-many).
 * Ciclo de vida de estado borrador → validado → aprobado/borrado.
 */
class PlanAccion extends Model implements HasMedia
{
    use SoftDeletes, HasFactory, InteractsWithMedia, HasVisibilityScope;

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
            if (!$plan->estado_id) {
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
}