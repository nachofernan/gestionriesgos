<?php

namespace App\Models\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Concerns\HasVisibilityScope;
use App\Models\User;
use App\Services\Auditoria\EvaluacionPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Plan de Acción asociado a uno o más Riesgo y a las Tarea que lo ejecutan (ambas many-to-many).
 * Ciclo de vida de estado borrador → validado → aprobado/borrado.
 */
class PlanAccion extends Model implements HasMedia
{
    use HasFactory, HasVisibilityScope, InteractsWithMedia, SoftDeletes;

    protected $table = 'planes_accion';

    protected $fillable = [
        'nombre',
        'descripcion',
        'evaluacion_estimada',
        'evaluacion_final',
        'estado_id',
        'interno',
        'user_id',
        'area_id',
    ];

    protected $casts = [
        'interno' => 'boolean',
        'evaluacion_estimada' => 'array',
        'evaluacion_final' => 'array',
    ];

    protected static function booted()
    {
        // Requiere que exista el estado "borrador" (ver EstadoRiesgoSeeder).
        static::creating(function ($plan) {
            if (! $plan->estado_id) {
                $borrador = Estado::borrador();
                if ($borrador) {
                    $plan->estado_id = $borrador->id;
                }
            }
        });
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function actualizaciones(): MorphMany
    {
        return $this->morphMany(Actualizacion::class, 'actualizable')->latest('created_at');
    }

    // Firmas de la revisión de la carga inicial (D-024), una por gerencia.
    public function revisionesIniciales(): MorphMany
    {
        return $this->morphMany(RevisionInicial::class, 'revisable');
    }

    public function riesgos(): BelongsToMany
    {
        return $this->belongsToMany(Riesgo::class, 'plan_accion_riesgo')
            ->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function tareas(): BelongsToMany
    {
        return $this->belongsToMany(Tarea::class, 'plan_accion_tarea')
            ->withTimestamps();
    }

    // -------------------------------------------------------
    // Accessors
    // -------------------------------------------------------

    /**
     * Avance del plan como promedio del `porcentaje_avance` de sus tareas en estado
     * "aprobado" (0-100), o null si no tiene ninguna tarea aprobada. Las tareas en
     * borrador/validado son trabajo real pendiente pero no cuentan para el avance, y
     * las de estado "borrado" quedan fuera por completo. Es el valor de pantalla
     * (redondeado): la mitigación usa el promedio exacto, ver getReduccionEfectivaAttribute().
     * Consumir con `tareas.estado` eager-loaded.
     */
    public function getAvanceAttribute(): ?int
    {
        $aprobadas = $this->tareasAprobadas();

        if ($aprobadas->isEmpty()) {
            return null;
        }

        return (int) round($aprobadas->avg('porcentaje_avance'));
    }

    /** Las tareas que cuentan para el avance y la mitigación. Requiere `tareas.estado` cargado. */
    public function tareasAprobadas(): Collection
    {
        return $this->tareas->filter(fn ($tarea) => $tarea->estado?->nombre === 'aprobado');
    }

    public function estaCompleto(): bool
    {
        return $this->avance === 100;
    }

    /**
     * Regla de estado de si el plan descuenta del residual de sus riesgos: sólo si
     * está aprobado (cuánto descuenta lo dice reduccion_efectiva). Requiere
     * `estado` cargado. Test: un_plan_validado_al_100_no_afecta_el_valor_residual.
     */
    public function mitiga(): bool
    {
        return $this->estado?->nombre === 'aprobado';
    }

    /**
     * Cuánto reduce el plan cada eje según su valoración (D-049): la evaluación
     * final si ya está cargada, si no la estimada; sin ninguna, 0 y 0. Es lo que
     * reduce al 100% de avance, antes del tope de cada riesgo.
     * Tests: la_evaluacion_final_reemplaza_a_la_estimada, un_plan_sin_evaluacion_no_mitiga.
     *
     * @return array{impacto:int, probabilidad:int}
     */
    public function getReduccionNominalAttribute(): array
    {
        return EvaluacionPlan::calcular($this->evaluacion_final ?? $this->evaluacion_estimada);
    }

    /**
     * Reducción que aplica de verdad el plan: la nominal de cada eje en
     * proporción al promedio exacto de avance de sus tareas aprobadas, hacia
     * abajo y eje por eje (D-037, D-049). Sin tareas aprobadas, 0. El tope contra
     * el impacto y la probabilidad de cada riesgo lo pone Riesgo::mitigacionPorEjes().
     * Requiere `tareas.estado` cargado.
     * Tests: un_plan_al_80_reduce_cada_eje_en_proporcion_hacia_abajo,
     * un_plan_casi_completo_no_mitiga_el_valor_entero.
     *
     * @return array{impacto:int, probabilidad:int}
     */
    public function getReduccionEfectivaAttribute(): array
    {
        return $this->reduccionConTareas($this->tareasAprobadas());
    }

    private function reduccionConTareas(Collection $tareas): array
    {
        $suma = (int) $tareas->sum('porcentaje_avance');

        return array_map(fn (int $eje) => self::aplicarAvance($eje, $suma, $tareas->count()), $this->reduccion_nominal);
    }

    /**
     * El plan terminó (D-049): tareas al 100% y la evaluación final ya aplicada.
     * Requiere `tareas.estado` cargado. Test: con_la_evaluacion_final_aprobada_el_plan_queda_finalizado.
     */
    public function getFinalizadoAttribute(): bool
    {
        return $this->evaluacion_final !== null && $this->estaCompleto();
    }

    /**
     * Regla única del redondeo de los planes, por eje: floor(nominal × suma /
     * (cantidad × 100)), en enteros para que un 99,67% no se convierta en 100 (D-037).
     */
    public static function aplicarAvance(int $nominal, int $sumaAvance, int $cantidad): int
    {
        return $cantidad === 0 ? 0 : intdiv($nominal * $sumaAvance, $cantidad * 100);
    }

    // -------------------------------------------------------
    // Proyección del borrador interno (D-044)
    // -------------------------------------------------------

    /**
     * Tareas que cuentan en la proyección: las aprobadas más las borradores
     * internas. Una validada o un borrador sin flag se pueden asociar, pero no
     * mueven el número. Requiere `tareas.estado` cargado.
     * Test: una_tarea_sin_flag_o_validada_no_mueve_el_avance_proyectado.
     */
    public function tareasProyectadas(): Collection
    {
        return $this->tareas->filter(fn ($tarea) => $tarea->estado?->nombre === 'aprobado'
            || ($tarea->interno && $tarea->estado?->nombre === 'borrador'));
    }

    /** Avance de pantalla de la proyección, ver getAvanceAttribute(). */
    public function getAvanceProyectadoAttribute(): ?int
    {
        $tareas = $this->tareasProyectadas();

        return $tareas->isEmpty() ? null : (int) round($tareas->avg('porcentaje_avance'));
    }

    /** Ver Control::mitigaProyectado(). */
    public function mitigaProyectado(): bool
    {
        return $this->mitiga() || ($this->interno && $this->estado?->nombre === 'borrador');
    }

    /**
     * Como reduccion_efectiva pero con las tareas proyectadas, mismo redondeo
     * hacia abajo (D-037). Tests: el_residual_proyectado_suma_lo_aprobado_y_lo_interno,
     * una_tarea_sin_flag_o_validada_no_mueve_el_avance_proyectado.
     *
     * @return array{impacto:int, probabilidad:int}
     */
    public function getReduccionEfectivaProyectadaAttribute(): array
    {
        return $this->reduccionConTareas($this->tareasProyectadas());
    }

    /**
     * Fecha de vencimiento del plan: la más próxima entre sus tareas todavía
     * pendientes (`porcentaje_avance` < 100), vigentes (no "borrado"). Una vez
     * que una tarea llega al 100% deja de contar, aunque su fecha haya quedado
     * en el pasado. null si no hay ninguna pendiente con fecha. Consumir con
     * `tareas.estado` eager-loaded.
     */
    public function getVencimientoAttribute(): ?Carbon
    {
        $fecha = $this->tareas
            ->reject(fn ($tarea) => $tarea->estado?->nombre === 'borrado')
            ->where('porcentaje_avance', '<', 100)
            ->whereNotNull('fecha')
            ->min('fecha');

        return $fecha ? Carbon::parse($fecha) : null;
    }

    /**
     * Un plan está vencido cuando su tarea pendiente más próxima ya pasó de
     * fecha (ver getVencimientoAttribute). Cubierto por
     * un_plan_esta_vencido_si_alguna_tarea_pendiente_paso_su_fecha.
     */
    public function getEstaVencidoAttribute(): bool
    {
        return $this->vencimiento !== null && $this->vencimiento->lt(now()->startOfDay());
    }

    /**
     * Motivo por el que este plan no se puede rechazar (null si se puede): es el
     * único plan validado o aprobado de algún riesgo validado o aprobado con
     * respuesta mitigar, que sin él quedaría incumpliendo
     * Riesgo::motivosBloqueoValidacion() (D-031). Lo consulta PlanAccionController::rechazar().
     * Test: no_se_rechaza_el_unico_plan_valido_de_un_riesgo_validado_que_mitiga.
     */
    public function motivoBloqueoRechazo(string $accion = 'rechazar'): ?string
    {
        $vigentes = ['validado', 'aprobado'];

        $riesgo = $this->riesgos()->with(['estado', 'planesAccion.estado'])->get()
            ->filter(fn (Riesgo $r) => in_array($r->estado?->nombre, $vigentes) && $r->respuesta === RespuestaRiesgo::Mitigar)
            ->first(fn (Riesgo $r) => ! $r->planesAccion->contains(
                fn (PlanAccion $p) => $p->id !== $this->id && in_array($p->estado?->nombre, $vigentes)
            ));

        return $riesgo
            ? "No se puede {$accion}: es el único plan vigente del riesgo «{$riesgo->nombre}», que se mitiga con un plan. Primero hay que resolver ese riesgo."
            : null;
    }

    /**
     * Motivo por el que el plan no se puede eliminar (null si se puede, D-047): tiene
     * que estar aprobado y no estar asociado a ningún riesgo vigente salvo
     * $juntoConRiesgoId, el riesgo con el que se propuso eliminarlo. Se consulta al
     * ofrecerlo (Riesgo::dependientesEliminables()) y otra vez al aplicar.
     */
    public function motivoBloqueoEliminacion(?int $juntoConRiesgoId = null): ?string
    {
        if ($this->estado?->nombre !== 'aprobado') {
            return 'No está aprobado.';
        }

        $otros = $this->riesgos()
            ->where('riesgos.id', '!=', $juntoConRiesgoId ?? 0)
            ->where('riesgos.estado_id', '!=', Estado::borrado()->id)
            ->exists();

        return $otros ? 'También está asociado a otros riesgos.' : null;
    }

    /** Tareas que caen con el plan al eliminarlo: las que no están en otro plan vigente (D-047). */
    public function tareasQueCaen(): Collection
    {
        $borrado = Estado::borrado()->id;

        return $this->tareas()
            ->where('tareas.estado_id', '!=', $borrado)
            ->whereDoesntHave('planesAccion', fn ($q) => $q->where('planes_accion.id', '!=', $this->id)->where('planes_accion.estado_id', '!=', $borrado))
            ->get();
    }
}
