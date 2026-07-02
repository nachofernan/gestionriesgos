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
 * Riesgo con código correlativo auto-generado. `valor_total` = impacto +
 * probabilidad; `valor_residual` descuenta la mitigación de los controles
 * asociados; `clasificacion_total`/`clasificacion_residual` traducen esos
 * valores a bajo/moderado/mayor criticidad (ver clasificacion()). Ciclo de vida
 * de estado borrador → validado → activo/borrado.
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
        'tipo_riesgo_id',
        'estado_id',
        'user_id',
        'area_id',
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
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
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
}