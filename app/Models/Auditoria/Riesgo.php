<?php

namespace App\Models\Auditoria;

use App\Enums\Auditoria\Condicion;
use App\Enums\Auditoria\MetodoEvaluacion;
use App\Enums\Auditoria\Responsabilidad;
use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\Temporalidad;
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
        'evaluacion',
        'temporalidad',
        'condicion',
        'responsabilidad',
        'mayor_criticidad',
        'respuesta',
        'fundamento',
        'fundamento_dimension',
        'tipo_riesgo_id',
        'estado_id',
        'interno',
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
        'evaluacion' => 'array',
        'interno' => 'boolean',
        'temporalidad' => Temporalidad::class,
        'condicion' => Condicion::class,
        'responsabilidad' => Responsabilidad::class,
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
     * (null si puede). Disponible en cualquier estado, y nunca para el comité
     * (su rol es aprobar, no reevaluar impacto/probabilidad). Sobre un aprobado
     * el recálculo es una propuesta que se aplica recién cuando la aprueba el
     * comité (D-046). Lo consultan RiesgoController::recalcular()/recalcularStore()
     * como bloqueo real, y las vistas para no ofrecer el link. Cubierto por
     * el_recalculo_de_un_riesgo_aprobado_queda_como_propuesta_hasta_que_aprueba_el_comite y
     * el_comite_no_puede_recalcular_ni_siquiera_en_borrador.
     */
    public function motivoBloqueoRecalculo(User $user): ?string
    {
        if ($user->esComite()) {
            return 'El comité no puede recalcular impacto y probabilidad.';
        }

        return null;
    }

    /**
     * Motivo por el que el riesgo no se puede eliminar (null si se puede, D-047): la
     * eliminación por propuesta es sólo para lo aprobado. Borrador y validado se
     * sacan con el rechazo con motivo (D-031). Se revisa al proponer
     * (RiesgoPolicy::delete()) y al aplicar (Actualizacion::aplicarEliminacion()).
     */
    public function motivoBloqueoEliminacion(): ?string
    {
        return $this->estado?->nombre === 'aprobado' ? null : 'Sólo se elimina un riesgo aprobado.';
    }

    /**
     * Controles y planes del riesgo que se ofrecen para eliminar junto con él
     * (D-047). Sólo se pueden elegir los que $user puede eliminar, están aprobados,
     * no se asocian a otro riesgo vigente y no tienen ya una eliminación
     * pendiente. Los demás vienen con el motivo, para mostrarlos deshabilitados.
     * Cada plan trae las tareas que caerían con él. Lo consumen show() (modal) y
     * proponerEliminacion() (que valida contra los seleccionables).
     * Tests: solo_se_ofrecen_los_controles_y_planes_de_una_sola_asociacion,
     * no_se_puede_colar_un_control_compartido.
     *
     * @return array{controles: list<array>, planes: list<array>}
     */
    public function dependientesEliminables(User $user): array
    {
        $borrado = Estado::borrado()->id;
        $describir = function (Model $elemento) use ($user): array {
            $motivo = match (true) {
                ! $user->can('delete', $elemento) => 'No lo gestionás.',
                default => $elemento->motivoBloqueoEliminacion($this->id)
                    ?? (Actualizacion::eliminacionPendienteDe($elemento) ? 'Ya tiene una eliminación propuesta.' : null),
            };

            return ['id' => $elemento->id, 'nombre' => $elemento->nombre, 'seleccionable' => $motivo === null, 'motivo' => $motivo];
        };

        return [
            'controles' => $this->controles()->with('estado')->where('controles.estado_id', '!=', $borrado)->orderBy('nombre')->get()
                ->map($describir)->values()->all(),
            'planes' => $this->planesAccion()->with('estado')->where('planes_accion.estado_id', '!=', $borrado)->orderBy('nombre')->get()
                ->map(fn (PlanAccion $plan) => $describir($plan) + [
                    'tareas' => $plan->tareasQueCaen()->pluck('nombre')->all(),
                ])->values()->all(),
        ];
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
            ->using(ControlRiesgo::class)
            ->withPivot('efectividad')
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
     * Resta al valor_total lo que mitigan los planes de acción aprobados (por
     * eje, con tope en el impacto y la probabilidad del riesgo, D-050) y después
     * la mitigación efectiva de los controles (el valor del control regulado por
     * el tramo de evidencia de cada relación, D-051). Todo se redondea hacia abajo. Sólo
     * mitigan los controles y planes en estado "aprobado": uno en
     * borrador/validado no baja el valor del riesgo. El residual nunca baja de 0.
     * Consumir con `controles.estado` y
     * `planesAccion.estado`/`planesAccion.tareas.estado` eager-loaded.
     * Tests: primero_los_planes_por_eje_y_despues_los_controles,
     * el_desglose_de_mitigacion_separa_controles_y_planes_y_cuadra_con_el_residual.
     */
    public function getValorResidualAttribute(): int
    {
        return max(0, $this->valor_total - $this->mitigacion_controles - $this->mitigacion_planes);
    }

    /**
     * Parte del residual que descuentan los controles: sólo los que mitigan
     * (Control::mitiga(): los aprobados), cada uno con lo que descuenta su
     * relación con este riesgo (ControlRiesgo::mitigacionEfectiva(): valor del
     * control × tramo de evidencia, hacia abajo, por relación). Separada de
     * valor_residual para que la ficha pueda mostrar el desglose sin recalcularlo.
     * Tests: el_desglose_de_mitigacion_separa_controles_y_planes_y_cuadra_con_el_residual,
     * la_mitigacion_efectiva_se_redondea_por_relacion.
     */
    public function getMitigacionControlesAttribute(): int
    {
        return (int) $this->controles
            ->filter(fn ($control) => $control->mitiga())
            ->sum(fn ($control) => $control->pivot->mitigacionEfectiva((int) $control->mitigacion_default));
    }

    /**
     * Parte del residual que descuentan los planes: sólo los aprobados
     * (PlanAccion::mitiga()), cada uno con su reducción por eje en proporción a
     * su avance (PlanAccion::reduccion_efectiva, D-037), sumadas y topeadas en
     * cada eje del riesgo (mitigacionPorEjes(), D-050). Ver
     * getMitigacionControlesAttribute().
     * Tests: un_plan_mitiga_por_eje_hasta_el_valor_de_cada_eje,
     * dos_planes_que_superan_un_eje_quedan_topeados_en_ese_eje.
     */
    public function getMitigacionPlanesAttribute(): int
    {
        return $this->mitigacionPorEjes(
            $this->planesAccion->filter(fn ($plan) => $plan->mitiga())->map(fn ($plan) => $plan->reduccion_efectiva)
        );
    }

    /**
     * Regla única del tope por eje (D-050): suma por separado lo que reducen
     * $reducciones en impacto y en probabilidad, y cada suma no pasa del valor de
     * ese eje en el riesgo. Ej.: riesgo 5/5 con un plan 8 imp / 2 prob mitiga
     * 5 + 2 = 7. La usan los accessors oficial y proyectado, la preview de
     * GestionPlanes y lo que muestra cada plan por riesgo.
     * Test: un_plan_mitiga_por_eje_hasta_el_valor_de_cada_eje.
     *
     * @param  iterable<array{impacto:int, probabilidad:int}>  $reducciones
     */
    public function mitigacionPorEjes(iterable $reducciones): int
    {
        $impacto = $probabilidad = 0;
        foreach ($reducciones as $r) {
            $impacto += $r['impacto'];
            $probabilidad += $r['probabilidad'];
        }

        return min((int) $this->impacto, $impacto) + min((int) $this->probabilidad, $probabilidad);
    }

    /**
     * Residual proyectado de un borrador interno (D-044): igual que valor_residual,
     * pero también descuentan los controles y planes en borrador con flag (y un plan
     * cuenta también sus tareas internas). No reemplaza al oficial: sólo lo muestran
     * los elementos internos y el panel interno. Mismo eager loading que valor_residual.
     * Tests: el_residual_proyectado_suma_lo_aprobado_y_lo_interno,
     * un_borrador_sin_flag_o_un_validado_no_proyectan,
     * la_proyeccion_no_cambia_el_residual_oficial.
     */
    public function getValorResidualProyectadoAttribute(): int
    {
        return max(0, $this->valor_total - $this->mitigacion_controles_proyectada - $this->mitigacion_planes_proyectada);
    }

    /** Ver getValorResidualProyectadoAttribute() y Control::mitigaProyectado(). */
    public function getMitigacionControlesProyectadaAttribute(): int
    {
        return (int) $this->controles
            ->filter(fn ($control) => $control->mitigaProyectado())
            ->sum(fn ($control) => $control->pivot->mitigacionEfectiva((int) $control->mitigacion_default));
    }

    /** Ver getValorResidualProyectadoAttribute(), PlanAccion::reduccion_efectiva_proyectada y mitigacionPorEjes(). */
    public function getMitigacionPlanesProyectadaAttribute(): int
    {
        return $this->mitigacionPorEjes(
            $this->planesAccion->filter(fn ($plan) => $plan->mitigaProyectado())->map(fn ($plan) => $plan->reduccion_efectiva_proyectada)
        );
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

    public const MENSAJE_CRUCE_TIPO = 'No se puede cambiar entre un tipo ambiental y uno que no lo es: se evalúan con cuestionarios distintos.';

    /** Método con el que se evalúa: lo define su tipo (D-043). */
    public function metodoEvaluacion(): MetodoEvaluacion
    {
        return $this->tipoRiesgo?->metodo_evaluacion ?? MetodoEvaluacion::Clasica;
    }

    /**
     * Regla para cambiar el tipo de un riesgo existente: el tipo nuevo tiene que
     * usar el mismo método de evaluación que el actual. Si no, impacto y
     * probabilidad quedarían calculados con el wizard equivocado (D-043). La
     * consumen RiesgoController::update(), FichaRiesgo::guardar() y
     * GestionActualizaciones::guardar().
     * Tests: no_se_puede_pasar_un_riesgo_clasico_a_ambiental_desde_el_borrador,
     * no_se_puede_sacar_un_riesgo_de_ambiental_desde_la_ficha,
     * no_se_puede_proponer_cruzar_a_ambiental_desde_actualizaciones,
     * el_tipo_sigue_cambiando_libre_entre_tipos_clasicos.
     */
    public function reglaTipoRiesgo(): array
    {
        $mismoMetodo = TipoRiesgo::where('metodo_evaluacion', $this->metodoEvaluacion()->value)->pluck('id')->all();

        return ['exists:tipos_riesgo,id', Rule::in($mismoMetodo)];
    }
}
