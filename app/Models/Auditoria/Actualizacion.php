<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Auditoria\Estado;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\User;

/**
 * Registro polimórfico (actualizable: Riesgo, Control, Objetivo, PlanAccion o
 * Tarea) de un mensaje o propuesta de cambio, con su propio ciclo de vida
 * borrador → validado → aprobado/borrado. `data` guarda los campos/relaciones
 * propuestos. Las transiciones (marcarValidada/marcarAprobada/marcarRechazada)
 * y la aplicación de esos cambios (aplicarCambios) viven acá porque hasta ahora
 * estaban duplicadas en ActualizacionController, GestionActualizaciones y
 * ValidacionMasivaService.
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

    /**
     * Valida la actualización y, si la entidad relacionada ya estaba en
     * "validado", aplica los cambios en el mismo paso (no espera una
     * aprobación aparte).
     */
    public function marcarValidada(User $usuario): void
    {
        DB::transaction(function () use ($usuario) {
            $this->update(['estado_id' => Estado::validado()->id]);

            if ($this->actualizable?->estado?->nombre === 'validado') {
                $this->update(['data' => array_merge($this->data ?? [], ['activated_by' => $usuario->name])]);
                $this->fresh()->aplicarCambios();
            }
        });
    }

    /** Aprueba la actualización y aplica sus cambios, sin importar el estado de validación previo. */
    public function marcarAprobada(User $usuario): void
    {
        DB::transaction(function () use ($usuario) {
            $this->update([
                'estado_id' => Estado::aprobado()->id,
                'data'      => array_merge($this->data ?? [], ['activated_by' => $usuario->name]),
            ]);
            $this->fresh()->aplicarCambios();
        });
    }

    public function marcarRechazada(): void
    {
        $this->update(['estado_id' => Estado::borrado()->id]);
    }

    /**
     * Aplica sobre la entidad relacionada (`actualizable`) los cambios guardados en `data`:
     * actualiza los campos de `data['campos']` (o el objeto completo si viene en formato
     * legacy sin las claves `campos`/`relaciones`) y sincroniza las relaciones many-to-many
     * indicadas en `data['relaciones']` (sync/attach/detach). No hace nada si `data` está
     * vacío o si el tipo de actualización no es 'cambio'.
     */
    public function aplicarCambios(): void
    {
        $data = $this->data ?? [];
        if (empty($data)) return;

        $tipo = $data['tipo'] ?? null;
        if ($tipo !== null && $tipo !== 'cambio') return;

        $model = $this->actualizable;

        if (isset($data['campos']) || isset($data['relaciones'])) {
            if (!empty($data['campos'])) {
                $model->update($data['campos']);
            }
        } else {
            // Legacy format
            $model->update(collect($data)->except(['tipo', 'diff', 'activated_by'])->toArray());
        }

        if (!empty($data['relaciones'])) {
            foreach ($data['relaciones'] as $relacion => $ops) {
                if (isset($ops['sync']))   $model->$relacion()->sync($ops['sync']);
                if (isset($ops['attach'])) $model->$relacion()->attach($ops['attach']);
                if (isset($ops['detach'])) $model->$relacion()->detach($ops['detach']);
            }
        }
    }
}
