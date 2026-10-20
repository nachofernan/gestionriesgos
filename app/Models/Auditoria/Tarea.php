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
 * Tarea de ejecución de uno o más Plan de Acción (many-to-many). Ciclo de vida
 * de estado borrador → validado → aprobado/borrado; ver también
 * ActualizacionTareaController para el registro rápido de `porcentaje_avance`.
 */
class Tarea extends Model implements HasMedia
{
    use HasFactory, HasVisibilityScope, InteractsWithMedia, SoftDeletes;

    protected $table = 'tareas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'fecha',
        'porcentaje_avance',
        'estado_id',
        'user_id',
        'area_id',
    ];

    public $casts = [
        'fecha' => 'date',
    ];

    protected static function booted()
    {
        // Requiere que exista el estado "borrador" (ver EstadoRiesgoSeeder).
        static::creating(function ($tarea) {
            if (! $tarea->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $tarea->estado_id = $borrador->id;
                }
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

    public function planesAccion(): BelongsToMany
    {
        return $this->belongsToMany(PlanAccion::class, 'plan_accion_tarea')
            ->withTimestamps();
    }

    /**
     * Deja en la actividad de cada plan que contiene la tarea una entrada con su
     * cambio de estado ('validada' | 'aprobada' | 'rechazada'), quién y el motivo
     * (D-031). Append-only: no es una propuesta ni entra al ciclo (sin estado),
     * y no es una nota (lleva data). La llaman TareaController::validar/aprobar/rechazar
     * y ValidacionMasivaService (cascada desde el plan).
     * Tests: rechazar_la_tarea_lo_deja_en_la_actividad_del_plan_con_el_motivo,
     * validar_el_plan_en_cascada_lo_deja_en_la_actividad_del_plan.
     */
    public function registrarEnSusPlanes(string $evento, User $usuario, ?string $motivo = null): void
    {
        $mensaje = "La tarea «{$this->nombre}» fue {$evento} por {$usuario->name}".($motivo ? ": {$motivo}" : '');

        foreach ($this->planesAccion()->get() as $plan) {
            $plan->actualizaciones()->create([
                'user_id' => $usuario->id,
                'mensaje' => $mensaje,
                'estado_id' => null,
                'data' => array_filter([
                    'tipo' => 'estado_tarea',
                    'tarea_id' => $this->id,
                    'tarea' => $this->nombre,
                    'evento' => $evento,
                    'motivo' => $motivo,
                ]),
            ]);
        }
    }
}
