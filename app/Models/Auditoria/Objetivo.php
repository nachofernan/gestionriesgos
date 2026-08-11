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
 * Objetivo estratégico o PEIS (Plan Estratégico de Integridad Sostenible) asociado a uno o más Riesgo
 * (many-to-many). Ciclo de vida de estado borrador → validado → aprobado/borrado.
 */
class Objetivo extends Model implements HasMedia
{
    use HasFactory, HasVisibilityScope, InteractsWithMedia, SoftDeletes;

    protected $table = 'objetivos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'fecha_objetivo',
        'estrategico',
        'peis',
        'estado_id',
        'user_id',
        'area_id',
    ];

    protected $casts = [
        'fecha_objetivo' => 'date',
        'estrategico' => 'boolean',
        'peis' => 'boolean',
    ];

    protected static function booted()
    {
        // Requiere que exista el estado "borrador" (ver EstadoRiesgoSeeder).
        static::creating(function ($objetivo) {
            if (! $objetivo->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $objetivo->estado_id = $borrador->id;
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
        return $this->belongsToMany(Riesgo::class, 'objetivo_riesgo')
            ->withTimestamps();
    }

    public function peisItems(): BelongsToMany
    {
        return $this->belongsToMany(PeisItem::class, 'objetivo_peis_item')
            ->withTimestamps();
    }
}
