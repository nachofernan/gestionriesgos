<?php

namespace App\Models\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
            if (! $actualizacion->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $actualizacion->estado_id = $borrador->id;
                }
            }

            // El default useCurrent() de la migración devuelve UTC en SQLite
            // (no respeta APP_TIMEZONE): se fuerza acá para que quede en
            // horario local.
            if (! $actualizacion->created_at) {
                $actualizacion->created_at = now();
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

    public function validacionesGerencia(): HasMany
    {
        return $this->hasMany(ValidacionGerencia::class);
    }

    // -------------------------------------------------------
    // Doble validación (riesgos compartidos entre gerencias)
    // -------------------------------------------------------

    /**
     * true si esta propuesta pertenece a un riesgo con dos o más gerencias: sólo
     * en ese caso el cambio no se aplica de una y necesita el voto de todas las
     * gerencias asociadas. Para el resto de entidades (y riesgos mono-gerencia)
     * es false y rige el flujo de validación de siempre.
     */
    public function requiereDobleValidacion(): bool
    {
        $model = $this->actualizable;

        return $model instanceof Riesgo && $model->esMultigerencia();
    }

    /**
     * Registra (o cambia) el voto de la gerencia del usuario sobre esta propuesta.
     * `aprueba` = validar (true) / rechazar (false). Cada gerencia vota una sola
     * vez; el proponente vota a favor al crear la propuesta.
     */
    public function registrarVoto(User $usuario, bool $aprueba): void
    {
        $gerencia = $usuario->areaGerencia();
        if (! $gerencia) {
            return;
        }

        $this->validacionesGerencia()->updateOrCreate(
            ['area_id' => $gerencia->id],
            ['user_id' => $usuario->id, 'aprueba' => $aprueba],
        );
    }

    /** true cuando todas las gerencias asociadas al riesgo votaron a favor. */
    public function todasLasGerenciasValidaron(): bool
    {
        $gerenciaIds = $this->actualizable->gerenciaIds();
        if (empty($gerenciaIds)) {
            return false;
        }

        $aFavor = $this->validacionesGerencia()->where('aprueba', true)->pluck('area_id');

        return collect($gerenciaIds)->every(fn ($id) => $aFavor->contains($id));
    }

    /** Nombres de las gerencias que todavía no votaron a favor (para mostrar en la UI). */
    public function gerenciasPendientes(): Collection
    {
        $aFavor = $this->validacionesGerencia()->where('aprueba', true)->pluck('area_id');

        return $this->actualizable->areas()
            ->where('tipo', TipoArea::Gerencia)
            ->whereNotIn('areas.id', $aFavor)
            ->pluck('nombre');
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
                'data' => array_merge($this->data ?? [], ['activated_by' => $usuario->name]),
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
        if (empty($data)) {
            return;
        }

        $tipo = $data['tipo'] ?? null;
        if ($tipo !== null && $tipo !== 'cambio') {
            return;
        }

        $model = $this->actualizable;

        if (isset($data['campos']) || isset($data['relaciones'])) {
            if (! empty($data['campos'])) {
                $model->update($data['campos']);
            }
        } else {
            // Legacy format
            $model->update(collect($data)->except(['tipo', 'diff', 'activated_by'])->toArray());
        }

        if (! empty($data['relaciones'])) {
            foreach ($data['relaciones'] as $relacion => $ops) {
                if (isset($ops['sync'])) {
                    $model->$relacion()->sync($ops['sync']);
                }
                if (isset($ops['attach'])) {
                    $model->$relacion()->attach($ops['attach']);
                }
                if (isset($ops['detach'])) {
                    $model->$relacion()->detach($ops['detach']);
                }
            }
        }
    }
}
