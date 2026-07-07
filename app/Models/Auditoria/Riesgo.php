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
use App\Enums\Auditoria\RespuestaRiesgo;

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
    use SoftDeletes, HasFactory, InteractsWithMedia, HasVisibilityScope;

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
            if (!$riesgo->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $riesgo->estado_id = $borrador->id;
                }
            }
            if (!$riesgo->codigo) {
                $ultimo  = Riesgo::withTrashed()->whereNotNull('codigo')->orderByDesc('id')->first();
                $numero  = $ultimo ? (intval(preg_replace('/\D/', '', $ultimo->codigo)) + 1) : 1;
                $riesgo->codigo = 'R-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
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
     * Resta al valor_total la mitigación efectiva de cada control asociado: el
     * valor del pivot si fue ajustado para este riesgo puntual, o si no
     * mitigacion_default del control.
     */
    public function getValorResidualAttribute(): int
    {
        $mitigacionTotal = $this->controles->sum(function ($control) {
            return $control->pivot->mitigacion ?? $control->mitigacion_default;
        });

        return max(0, $this->valor_total - $mitigacionTotal);
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

        return ['etiqueta' => 'mayor criticidad', 'color' => 'rojo'];
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