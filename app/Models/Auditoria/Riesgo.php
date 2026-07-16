<?php

namespace App\Models\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Concerns\HasVisibilityScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Riesgo con código correlativo auto-generado. `valor_total` = impacto +
 * probabilidad; `valor_residual` descuenta la mitigación de los controles
 * asociados; `clasificacion_total`/`clasificacion_residual` traducen esos
 * valores a bajo/moderado/mayor criticidad (ver clasificacion()). `respuesta`
 * es la estrategia frente al riesgo (mitigar/evitar/compartir/aceptar, ver
 * RespuestaRiesgo). Ciclo de vida de estado borrador → validado → aprobado/borrado.
 */
class Riesgo extends Model implements HasMedia
{
    use HasFactory, HasVisibilityScope, InteractsWithMedia, SoftDeletes;

    protected $table = 'riesgos';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'impacto',
        'probabilidad',
        'mayor_criticidad',
        'respuesta',
        'tipo_riesgo_id',
        'estado_id',
        'user_id',
        'area_id',
    ];

    protected $casts = [
        'respuesta' => RespuestaRiesgo::class,
    ];

    protected $appends = ['valor_total', 'valor_residual', 'clasificacion_total', 'clasificacion_residual'];

    // -------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------

    public function tipoRiesgo(): BelongsTo
    {
        return $this->belongsTo(TipoRiesgo::class, 'tipo_riesgo_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    protected static function booted()
    {
        // Requiere que exista el estado "borrador" (ver EstadoRiesgoSeeder).
        // También asigna el código correlativo (R-0001, R-0002, ...) si no vino seteado,
        // incluyendo los borrados lógicamente (withTrashed) para no reutilizar códigos.
        static::creating(function ($riesgo) {
            if (! $riesgo->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $riesgo->estado_id = $borrador->id;
                }
            }
            if (! $riesgo->codigo) {
                $ultimo = Riesgo::withTrashed()->whereNotNull('codigo')->orderByDesc('id')->first();
                $numero = $ultimo ? (intval(preg_replace('/\D/', '', $ultimo->codigo)) + 1) : 1;
                $riesgo->codigo = 'R-'.str_pad($numero, 4, '0', STR_PAD_LEFT);
            }
        });

        // El área del creador queda como primera gerencia con permisos sobre el
        // riesgo; sin esto, un riesgo recién creado no tendría ninguna gerencia
        // asociada en area_riesgo y nadie podría gestionarlo.
        static::created(function ($riesgo) {
            if ($riesgo->area_id) {
                $riesgo->areas()->syncWithoutDetaching([$riesgo->area_id]);
            }
        });
    }

    /**
     * Sobrescribe HasVisibilityScope::scopeVisiblePara(): un riesgo es de "área
     * propia" si CUALQUIERA de sus gerencias asociadas (area_riesgo) cae en el
     * subárbol del usuario, no solo su area_id. El resto de la trait (público
     * para aprobado/validado, comité solo ve público) se mantiene igual.
     */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        if (! $user->area_id) {
            return $query;
        }

        $publicoIds = array_filter([Estado::aprobado()?->id, Estado::validado()?->id]);

        if ($user->esComite()) {
            return $query->whereIn('estado_id', $publicoIds);
        }

        $propiaIds = $user->area->obtenerIdsSubarbol();

        return $query->where(fn ($q) => $q->whereIn('estado_id', $publicoIds)
            ->orWhereHas('areas', fn ($sub) => $sub->whereIn('areas.id', $propiaIds))
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Gerencias a las que pertenece el riesgo, más allá de la que lo creó
     * (area_id). Un riesgo puede pertenecer a varias; todas tienen los mismos
     * permisos de gestión (ver RiesgoPolicy). Se sincroniza con el área del
     * creador al crearse (ver booted()) y se administra después desde el show.
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_riesgo')->withTimestamps();
    }

    /**
     * Reemplaza a `$user->puedeGestionarArea($riesgo->area_id)` en RiesgoPolicy:
     * un usuario puede gestionar el riesgo si puede gestionar alguna de sus
     * gerencias asociadas. Sin gerencias asociadas (riesgo sin área), cualquiera
     * puede gestionarlo, igual que el comportamiento previo de puedeGestionarArea(null).
     */
    public function puedeGestionarAlgunaArea(User $user): bool
    {
        if ($this->areas->isEmpty()) {
            return true;
        }

        return $this->areas->contains(fn (Area $area) => $user->puedeGestionarArea($area->id));
    }

    public function controles(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'control_riesgo')
            ->withPivot('mitigacion')
            ->withTimestamps();
    }

    public function objetivos(): BelongsToMany
    {
        return $this->belongsToMany(Objetivo::class, 'objetivo_riesgo')
            ->withTimestamps();
    }

    public function actualizaciones(): MorphMany
    {
        return $this->morphMany(Actualizacion::class, 'actualizable')->latest('created_at');
    }

    public function planesAccion(): BelongsToMany
    {
        return $this->belongsToMany(PlanAccion::class, 'plan_accion_riesgo')
            ->withPivot('mitigacion')
            ->withTimestamps();
    }

    // -------------------------------------------------------
    // Accessors
    // -------------------------------------------------------

    public function getValorTotalAttribute(): int
    {
        return $this->impacto + $this->probabilidad;
    }

    /**
     * Resta al valor_total la mitigación efectiva de los controles asociados (el
     * valor del pivot si fue ajustado para este riesgo puntual, o mitigacion_default
     * del control) más la de los planes de acción que estén al 100% de avance. La
     * mitigación de un plan sólo cuenta cuando el plan está completo; hasta entonces
     * no descuenta nada. El residual nunca baja de 0.
     */
    public function getValorResidualAttribute(): int
    {
        $mitigacionControles = $this->controles->sum(function ($control) {
            return $control->pivot->mitigacion ?? $control->mitigacion_default;
        });

        $mitigacionPlanes = $this->planesAccion->sum(function ($plan) {
            return $plan->estaCompleto() ? ($plan->pivot->mitigacion ?? 0) : 0;
        });

        return max(0, $this->valor_total - $mitigacionControles - $mitigacionPlanes);
    }

    /**
     * Devuelve ['etiqueta' => 'bajo|moderado|critico', 'color' => 'verde|amarillo|rojo']
     * para cualquier valor numérico de riesgo (0-20).
     */
    public static function clasificacion(int $valor): array
    {
        if ($valor <= 9) {
            return ['etiqueta' => 'bajo', 'color' => 'verde'];
        }

        if ($valor <= 13) {
            return ['etiqueta' => 'moderado', 'color' => 'amarillo'];
        }

        return ['etiqueta' => 'critico', 'color' => 'rojo'];
    }

    public function getClasificacionTotalAttribute(): array
    {
        return static::clasificacion($this->valor_total);
    }

    public function getClasificacionResidualAttribute(): array
    {
        return static::clasificacion($this->valor_residual);
    }

    /**
     * Prerequisitos duros para pasar a "validado", más allá de la cascada de
     * ValidacionMasivaService: al menos un objetivo asociado, y si la respuesta
     * es mitigar, al menos un plan de acción. Devuelve los motivos de bloqueo
     * (vacío si puede validarse). Usado por RiesgoController::validar() y por
     * ValidacionMasivaService::ejecutar().
     */
    public function motivosBloqueoValidacion(): array
    {
        $motivos = [];

        if ($this->objetivos()->count() === 0) {
            $motivos[] = 'El riesgo debe tener al menos un objetivo asociado para poder validarse.';
        }

        if ($this->respuesta === RespuestaRiesgo::Mitigar && $this->planesAccion()->count() === 0) {
            $motivos[] = 'Un riesgo con respuesta "Reducir/Mitigar" debe tener al menos un plan de acción asociado para poder validarse.';
        }

        return $motivos;
    }
}
