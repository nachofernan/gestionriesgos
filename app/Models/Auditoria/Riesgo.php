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
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Riesgo. `valor_total` = impacto +
 * probabilidad; `valor_residual` descuenta la mitigación de los controles
 * asociados; `clasificacion_total`/`clasificacion_residual` traducen esos
 * valores a bajo/moderado/critico (ver clasificacion()). `respuesta` es la
 * estrategia frente al riesgo (mitigar/evitar/compartir/aceptar, ver
 * RespuestaRiesgo) y `fundamento` justifica esa elección: es obligatorio sólo
 * para compartir y aceptar (ver RespuestaRiesgo::exigenFundamento()).
 * Ciclo de vida de estado borrador → validado → aprobado/borrado.
 */
class Riesgo extends Model implements HasMedia
{
    use HasFactory, HasVisibilityScope, InteractsWithMedia, SoftDeletes;

    protected $table = 'riesgos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'impacto',
        'probabilidad',
        'mayor_criticidad',
        'respuesta',
        'fundamento',
        'fundamento_dimension',
        'tipo_riesgo_id',
        'estado_id',
        'user_id',
        'area_id',
        'validado_por_id',
        'validado_en',
        'aprobado_por_id',
        'aprobado_en',
        'rechazado_por_id',
        'rechazado_en',
    ];

    protected $casts = [
        'respuesta' => RespuestaRiesgo::class,
        'validado_en' => 'datetime',
        'aprobado_en' => 'datetime',
        'rechazado_en' => 'datetime',
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
        static::creating(function ($riesgo) {
            if (! $riesgo->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $riesgo->estado_id = $borrador->id;
                }
            }
        });
    }

    /**
     * Sesgo gerencial para paneles y listados de situación: cada usuario ve sólo
     * los riesgos de su gerencia y sub-áreas (la cascada del organigrama sobre
     * area_id), nunca los de gerencias hermanas o primas. El comité y
     * cualquier usuario sin área propia (superusuario) ven todo. A diferencia de
     * scopeVisiblePara(), NO trata lo aprobado/validado como público a toda la
     * organización —eso rompería el sesgo mostrando otras gerencias— y NO filtra
     * por estado: quien consume decide qué estados incluir. Estrenado como criterio
     * en la pantalla de Vencimientos; acá se extrae reusable para el PanelRiesgos.
     */
    public function scopeDeCascadaArea(Builder $query, User $user): Builder
    {
        if (! $user->area_id) {
            return $query;
        }

        $ids = $user->area->obtenerIdsSubarbol();

        return $query->whereIn('area_id', $ids);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    public function rechazadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Motivo por el que $user no puede recalcular impacto/probabilidad ahora mismo
     * (null si puede). Disponible en cualquier estado salvo aprobado, y nunca
     * para el comité (su rol es aprobar, no reevaluar impacto/probabilidad).
     * Lo consultan RiesgoController::recalcular()/recalcularStore() como bloqueo
     * real, y las vistas para no ofrecer el link. Cubierto por
     * no_se_puede_recalcular_un_riesgo_ya_aprobado y
     * el_comite_no_puede_recalcular_ni_siquiera_en_borrador.
     */
    public function motivoBloqueoRecalculo(User $user): ?string
    {
        if ($this->estado?->nombre === 'aprobado') {
            return 'El riesgo ya fue aprobado: el impacto y la probabilidad no pueden recalcularse.';
        }

        if ($user->esComite()) {
            return 'El comité no puede recalcular impacto y probabilidad.';
        }

        return null;
    }

    /** Sedes a las que corresponde el riesgo (al menos una, D-029). Informativa: no da acceso. */
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'riesgo_sede')->withTimestamps();
    }

    /** Dimensiones del riesgo (al menos una, D-032). Informativa: no da acceso ni cambia el valor. */
    public function dimensiones(): BelongsToMany
    {
        return $this->belongsToMany(Dimension::class, 'dimension_riesgo')->withTimestamps();
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

    // Firmas de la revisión de la carga inicial (D-024) ante la gerencia del riesgo.
    public function revisionesIniciales(): MorphMany
    {
        return $this->morphMany(RevisionInicial::class, 'revisable');
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
     * del control) más la de los planes de acción que estén aprobados y al 100% de
     * avance. Sólo mitigan los controles y planes en estado "aprobado": uno en
     * borrador/validado no baja el valor del riesgo, aunque esté completo. El
     * residual nunca baja de 0. Consumir con `controles.estado` y
     * `planesAccion.estado`/`planesAccion.tareas.estado` eager-loaded.
     */
    public function getValorResidualAttribute(): int
    {
        return max(0, $this->valor_total - $this->mitigacion_controles - $this->mitigacion_planes);
    }

    /**
     * Parte del residual que descuentan los controles: sólo los que mitigan
     * (Control::mitiga(): los aprobados), con la mitigación
     * del pivot (o mitigacion_default del control). Separada de
     * valor_residual para que la ficha pueda mostrar el desglose sin recalcularlo.
     * Test: el_desglose_de_mitigacion_separa_controles_y_planes_y_cuadra_con_el_residual.
     */
    public function getMitigacionControlesAttribute(): int
    {
        return (int) $this->controles
            ->filter(fn ($control) => $control->mitiga())
            ->sum(fn ($control) => $control->pivot->mitigacion ?? $control->mitigacion_default);
    }

    /**
     * Parte del residual que descuentan los planes: sólo los aprobados y al 100%
     * de avance, con la mitigación del pivot. Ver getMitigacionControlesAttribute().
     */
    public function getMitigacionPlanesAttribute(): int
    {
        return (int) $this->planesAccion->sum(function ($plan) {
            $aporta = $plan->estado?->nombre === 'aprobado' && $plan->estaCompleto();

            return $aporta ? ($plan->pivot->mitigacion ?? 0) : 0;
        });
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
     * ValidacionMasivaService: al menos un objetivo asociado que ya esté
     * validado (o aprobado), y si la respuesta es mitigar, al menos un plan de
     * acción también validado (o aprobado) — a ninguno de los dos alcanza con
     * que exista, porque un objetivo o un plan en borrador no son un respaldo
     * creíble. Devuelve los motivos de bloqueo (vacío si puede validarse).
     * Usado por RiesgoController::validar() y por
     * ValidacionMasivaService::ejecutar().
     */
    public function motivosBloqueoValidacion(): array
    {
        $motivos = [];

        $objetivos = $this->objetivos()->with('estado')->get();

        if ($objetivos->isEmpty()) {
            $motivos[] = 'El riesgo debe tener al menos un objetivo asociado para poder validarse.';
        } elseif (! $objetivos->contains(fn ($objetivo) => in_array($objetivo->estado?->nombre, ['validado', 'aprobado']))) {
            $motivos[] = 'El objetivo asociado debe estar validado (o aprobado) para poder validar este riesgo.';
        }

        if ($this->respuesta === RespuestaRiesgo::Mitigar) {
            $planes = $this->planesAccion()->with('estado')->get();

            if ($planes->isEmpty()) {
                $motivos[] = 'Un riesgo con respuesta "Reducir/Mitigar" debe tener al menos un plan de acción asociado para poder validarse.';
            } elseif (! $planes->contains(fn ($plan) => in_array($plan->estado?->nombre, ['validado', 'aprobado']))) {
                $motivos[] = 'El plan de acción asociado debe estar validado (o aprobado) para poder validar este riesgo.';
            }
        }

        return $motivos;
    }

    /**
     * Prerequisito duro para pasar a "aprobado": al menos uno de los objetivos
     * asociados debe estar aprobado, y si la respuesta es mitigar, al menos uno
     * de los planes de acción también — la misma exigencia de
     * motivosBloqueoValidacion() pero un escalón más arriba. Devuelve los
     * motivos de bloqueo (vacío si puede aprobarse). Usado por
     * RiesgoController::aprobar() y por ValidacionMasivaService::ejecutar().
     */
    public function motivosBloqueoAprobacion(): array
    {
        $motivos = [];

        $objetivos = $this->objetivos()->with('estado')->get();

        if (! $objetivos->contains(fn ($objetivo) => $objetivo->estado?->nombre === 'aprobado')) {
            $motivos[] = 'El objetivo asociado debe estar aprobado para poder aprobar este riesgo.';
        }

        if ($this->respuesta === RespuestaRiesgo::Mitigar) {
            $planes = $this->planesAccion()->with('estado')->get();

            if (! $planes->contains(fn ($plan) => $plan->estado?->nombre === 'aprobado')) {
                $motivos[] = 'Un riesgo con respuesta "Reducir/Mitigar" debe tener su plan de acción aprobado para poder aprobarse.';
            }
        }

        return $motivos;
    }

    /**
     * Reglas de validación para `respuesta` según el tipo de riesgo elegido:
     * obligatoria siempre (ver D-012, docs/DECISIONES.md); un TipoRiesgo con
     * `restringe_respuesta` (Corrupción) además no admite
     * RespuestaRiesgo::restringidas(). Única fuente de verdad: la consumen
     * RiesgoController::store/update y GestionActualizaciones::guardar() (cambio
     * de respuesta sobre un riesgo ya validado/aprobado) para no duplicar la regla.
     *
     * @return array<int, mixed>
     */
    public static function reglaRespuesta(mixed $tipoRiesgoId): array
    {
        $reglas = ['required', Rule::enum(RespuestaRiesgo::class)];

        if (TipoRiesgo::whereKey($tipoRiesgoId)->value('restringe_respuesta')) {
            $reglas[] = Rule::notIn(array_column(RespuestaRiesgo::restringidas(), 'value'));
        }

        return $reglas;
    }

    /** Ver reglaRespuesta(): `fundamento` es obligatorio sólo para las respuestas de RespuestaRiesgo::exigenFundamento(). */
    public static function reglaFundamento(): array
    {
        $exigen = array_column(RespuestaRiesgo::exigenFundamento(), 'value');

        return ['nullable', 'string', 'required_if:respuesta,'.implode(',', $exigen)];
    }

    /**
     * Con dos o más dimensiones hay que fundamentar por qué (D-032); con una sola
     * el fundamento no aplica. La consumen RiesgoController y FichaRiesgo.
     * Test: con_dos_dimensiones_el_fundamento_es_obligatorio.
     */
    public static function reglaFundamentoDimension(array $dimensiones): array
    {
        return ['nullable', 'string', Rule::requiredIf(count($dimensiones) >= 2)];
    }
}
