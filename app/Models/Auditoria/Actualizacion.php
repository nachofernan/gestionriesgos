<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Auditoria\Estado;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\User;

/**
 * Registro polimórfico (actualizable: Riesgo, Control, Objetivo, PlanAccion o
 * Tarea) de un mensaje o propuesta de cambio, con su propio ciclo de vida
 * borrador → validado → aprobado/borrado. `data` guarda los campos/relaciones
 * propuestos (ver los distintos aplicarCambios() en los controladores/Livewire
 * que consumen este modelo).
 */
class Actualizacion extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'actualizaciones';

    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'user_id',
        'mensaje',
        'data',
        'estado_id',
    ];

    protected static function booted()
    {
        // Requiere que exista el estado "borrador" (ver EstadoRiesgoSeeder).
        static::creating(function ($actualizacion) {
            if (!$actualizacion->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $actualizacion->estado_id = $borrador->id;
                }
            }
        });
    }

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('adjuntos');
    }

    public function actualizable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }
}
