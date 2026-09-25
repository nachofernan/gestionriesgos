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
 * Control de mitigación aplicable a uno o más Riesgo (many-to-many con
 * `mitigacion` en el pivot: el valor de mitigación efectivo para ese riesgo
 * puntual, que puede diferir de `mitigacion_default`). Ciclo de vida de estado
 * borrador → validado → aprobado/borrado.
 */
class Control extends Model implements HasMedia
{
    use HasFactory, HasVisibilityScope, InteractsWithMedia, SoftDeletes;

    protected $table = 'controles';

    protected $fillable = [
        'nombre',
        'descripcion',
        'mitigacion_default',
        'estado_id',
        'user_id',
        'area_id',
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

    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'control_riesgo')
            ->withPivot('mitigacion')
            ->withTimestamps();
    }

    /**
     * Lleva la mitigación por defecto (ya guardada) a todas las asociaciones con
     * riesgos: la opción "aplicar también a los riesgos asociados" al cambiar el
     * default. Pisa todas, incluidos riesgos compartidos y de otras gerencias, sin
     * pasar por su doble validación (ver DECISIONES). Cada riesgo cuyo valor cambia
     * recibe una Actualizacion ya aplicada con el mismo diff que una propuesta de
     * mitigación, para que su historial cuente el cambio. $antes es el default
     * previo, que vale para las asociaciones con pivot en null. La llaman
     * Actualizacion::registrarCambioCampos()/aplicarCambios() y ControlController::update().
     * Tests: cambiar_el_default_tildado_pisa_todas_las_asociaciones_y_baja_el_residual,
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

                $this->riesgos()->updateExistingPivot($riesgo->id, ['mitigacion' => $nuevo]);

                $riesgo->actualizaciones()->create([
                    'user_id' => $usuario->id,
                    'mensaje' => "Mitigación de «{$this->nombre}»: {$anterior} → {$nuevo} (cambio del default del control)",
                    'estado_id' => Estado::aprobado()->id,
                    'aprobado_por_id' => $usuario->id,
                    'aprobado_en' => now(),
                    'data' => [
                        'tipo' => 'cambio',
                        'origen' => 'default_control',
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
        });
    }
}
