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
 * Control de mitigación aplicable a uno o más Riesgo (many-to-many con
 * `mitigacion` en el pivot: el valor de mitigación efectivo para ese riesgo
 * puntual, que puede diferir de `mitigacion_default`). Ciclo de vida de estado
 * borrador → validado → activo/borrado.
 */
class Control extends Model implements HasMedia
{
    use SoftDeletes, HasFactory, InteractsWithMedia, HasVisibilityScope;

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
            if (!$control->estado_id) {
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
}