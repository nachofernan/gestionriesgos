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
 * valores a bajo/moderado/critico (ver clasificacion()). `respuesta` es la
 * estrategia frente al riesgo (mitigar/evitar/compartir/aceptar, ver
 * RespuestaRiesgo) y `fundamento` justifica esa elección: es obligatorio para
 * las respuestas que no reducen el riesgo (ver RespuestaRiesgo::exigenFundamento()).
 * Ciclo de vida de estado borrador → validado → aprobado/borrado.
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
        'fundamento',
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

        // Al crearse, area_riesgo queda con dos cosas: el área puntual del creador
        // (para que quien lo creó conserve acceso a su propio borrador, ya que
        // esAncestroOIgual sólo reconoce a un usuario cuya área sea ancestro-o-igual
        // de la del pivot) y la gerencia resuelta de esa área (para que la gerencia
        // responsable quede explícitamente asociada). Si el área ya es gerencia,
        // ambas coinciden y array_unique evita el duplicado. Sin esto, nadie podría
        // gestionar el riesgo recién creado.
        static::created(function ($riesgo) {
            if ($riesgo->area_id) {
                $gerenciaId = $riesgo->area?->gerencia()?->id;
                $ids = array_unique(array_filter([$riesgo->area_id, $gerenciaId]));
                // Ambas entradas son la gerencia propia (nunca ajena en este punto):
                // van con gerencia_ajena = false. El flag sólo pasa a true para
                // gerencias agregadas después vía GestionAreas (ver su guardar()).
                $riesgo->areas()->syncWithoutDetaching(
                    collect($ids)->mapWithKeys(fn ($id) => [$id => ['gerencia_ajena' => false]])->all()
                );
            }
        });
    }

    /**
     * Sobrescribe HasVisibilityScope::scopeVisiblePara(): un riesgo es de "área
     * propia" si CUALQUIERA de sus gerencias asociadas (area_riesgo) cae en el
     * subárbol del usuario, no solo su area_id. Además, si una gerencia AJENA
     * (gerencia_ajena = true, marcada al asociar una gerencia distinta a la de
     * origen del riesgo) es ancestro-o-igual del área del usuario, todos los que
     * cuelgan de esa gerencia también lo ven — así el sesgo gerencial no se limita
     * al gerente, sino a toda su gente. El resto de la trait (público para
     * aprobado/validado, comité solo ve público) se mantiene igual.
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
        $lineaAscendenteIds = $user->area->obtenerIdsAncestros();

        return $query->where(fn ($q) => $q->whereIn('estado_id', $publicoIds)
            ->orWhereHas('areas', fn ($sub) => $sub->whereIn('areas.id', $propiaIds))
            ->orWhereHas('areas', fn ($sub) => $sub->where('area_riesgo.gerencia_ajena', true)
                ->whereIn('areas.id', $lineaAscendenteIds))
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
        return $this->belongsToMany(Area::class, 'area_riesgo')
            ->withPivot('gerencia_ajena')
            ->withTimestamps();
    }

    /**
     * Reemplaza a `$user->puedeGestionarArea($riesgo->area_id)` en RiesgoPolicy:
     * un usuario puede gestionar el riesgo si puede gestionar alguna de sus
     * gerencias asociadas. Sin gerencias asociadas (riesgo sin área), cualquiera
     * puede gestionarlo, igual que el comportamiento previo de puedeGestionarArea(null).
     *
     * Regla adicional: si una gerencia asociada está marcada como AJENA
     * (gerencia_ajena en el pivot, ver GestionAreas::guardar()) y es ancestro-o-igual
     * del área del usuario, éste también puede gestionar. Esto amplía el acceso a
     * TODA la gente que cuelga de una gerencia ajena, no sólo a quien la tiene como
     * área ancestro-o-igual "hacia abajo". No aplana el comportamiento dentro de la
     * misma gerencia: la gerencia propia del riesgo nunca lleva gerencia_ajena = true,
     * así que este camino sólo se activa para gerencias explícitamente ajenas.
     */
    public function puedeGestionarAlgunaArea(User $user): bool
    {
        if ($this->areas->isEmpty()) {
            return true;
        }

        return $this->areas->contains(function (Area $area) use ($user) {
            if ($user->puedeGestionarArea($area->id)) {
                return true;
            }

            return $area->pivot->gerencia_ajena && $area->esAncestroOIgual($user->area_id);
        });
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
