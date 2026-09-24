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
        'validado_por_id',
        'validado_en',
        'aprobado_por_id',
        'aprobado_en',
        'rechazado_por_id',
        'rechazado_en',
    ];

    protected static function booted()
    {
        // Requiere que exista el estado "borrador" (ver EstadoRiesgoSeeder). Si el
        // creador pasó `estado_id` explícitamente (aunque sea null, como hace un
        // mensaje puro sin ciclo de validación: ver GestionActualizaciones::guardar()),
        // se respeta tal cual — el default sólo aplica cuando ni se mencionó la clave.
        static::creating(function ($actualizacion) {
            if (! array_key_exists('estado_id', $actualizacion->getAttributes())) {
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
        'validado_en' => 'datetime',
        'aprobado_en' => 'datetime',
        'rechazado_en' => 'datetime',
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

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    /**
     * Estado inicial de una propuesta de cambio según el rol de quien la crea,
     * relativo al estado de la entidad editada: comité editando algo ya aprobado
     * arranca directamente aprobado (se aplica al toque); gerente o comité en
     * cualquier otro caso saltean el borrador y arrancan validado; el resto
     * arranca en borrador. La usan GestionActualizaciones::guardar() (cambios de
     * campo genéricos) y RiesgoController::recalcularStore() (wizard de
     * recálculo de impacto/probabilidad), para no duplicar la regla.
     */
    public static function estadoInicialParaCambio(User $usuario, ?string $estadoEntidad): int
    {
        if ($estadoEntidad === 'aprobado' && $usuario->esComite()) {
            return Estado::aprobado()->id;
        }

        if ($usuario->esGerente() || $usuario->esComite()) {
            return Estado::validado()->id;
        }

        return Estado::borrador()->id;
    }

    /**
     * Nota suelta sobre la entidad (mensaje, con o sin adjuntos que se agregan
     * después): estado_id null la deja fuera del ciclo borrador→validado→aprobado,
     * así no aparece en Pendientes ni ofrece validar/rechazar (ver
     * ActualizacionPolicy). La usan GestionActualizaciones y ConversacionRiesgo.
     * Quien llama ya autorizó 'update' sobre la entidad.
     */
    public static function registrarNota(Model $entidad, User $usuario, string $mensaje): self
    {
        return $entidad->actualizaciones()->create([
            'user_id' => $usuario->id,
            'mensaje' => $mensaje,
            'estado_id' => null,
            'data' => null,
        ]);
    }

    /**
     * Registra un cambio de campos sobre la entidad. Nace en el estado que le toca
     * al rol (estadoInicialParaCambio()), o en borrador con el voto a favor del
     * proponente si el riesgo es compartido (Riesgo::cambioRequiereDobleValidacion());
     * se aplica en el acto si nace aprobado, o validado sobre una entidad validada.
     * Guarda el diff (antes → después) que pintan el historial y las tarjetas de
     * propuesta. Sale de GestionActualizaciones::guardar() para que también lo use
     * FichaRiesgo. Quien llama ya validó los campos y autorizó 'update'.
     * Tests: un_empleado_edita_la_ficha_y_queda_propuesta_solo_con_los_campos_que_cambiaron,
     * en_un_riesgo_compartido_la_ficha_propone_con_el_voto_del_proponente.
     */
    public static function registrarCambioCampos(Model $entidad, User $usuario, string $mensaje, array $campos): self
    {
        $estadoEntidad = $entidad->estado?->nombre;
        $dobleValidacion = $entidad instanceof Riesgo && $entidad->cambioRequiereDobleValidacion($usuario);
        $estadoId = $dobleValidacion ? Estado::borrador()->id : static::estadoInicialParaCambio($usuario, $estadoEntidad);

        $diff = [];
        foreach ($campos as $campo => $nuevo) {
            $antes = $entidad->$campo;
            // respuesta castea a RespuestaRiesgo (BackedEnum): sin esto, comparar
            // el enum contra el string crudo del select nunca da igual y el diff
            // mostraría "cambio" aunque se reeligiera el mismo valor.
            if ($antes instanceof \BackedEnum) {
                $antes = $antes->value;
            }
            if ($antes != $nuevo) {
                $diff[$campo] = ['antes' => $antes, 'despues' => $nuevo];
            }
        }

        $data = ['tipo' => 'cambio', 'campos' => $campos];
        if (! empty($diff)) {
            $data['diff'] = ['campos' => $diff];
        }

        $aplicar = ! $dobleValidacion && ($estadoId === Estado::aprobado()->id
            || ($estadoId === Estado::validado()->id && $estadoEntidad === 'validado'));
        if ($aplicar) {
            $data['activated_by'] = $usuario->name;
        }

        return DB::transaction(function () use ($entidad, $usuario, $mensaje, $campos, $estadoId, $data, $aplicar, $dobleValidacion) {
            $actualizacion = $entidad->actualizaciones()->create([
                'user_id' => $usuario->id,
                'mensaje' => $mensaje,
                'estado_id' => $estadoId,
                'data' => $data,
            ]);

            if ($aplicar) {
                $entidad->update($campos);
            }

            if ($dobleValidacion) {
                $actualizacion->registrarVoto($usuario, true);
            }

            return $actualizacion;
        });
    }

    /**
     * Propuestas de cambio que todavía no se aplicaron a su entidad: tipo
     * "cambio", en borrador (espera validación) o validada sin `activated_by`
     * (espera al comité). Con `$parte` se acota a las que tocan esa parte de la
     * entidad: una relación ('objetivos', 'controles', 'planesAccion', 'areas') o
     * 'campos'. Lo consumen los bloques de riesgo/show para mostrar lo propuesto
     * debajo de lo vigente. Solo lectura.
     * Test: propuestas_pendientes_incluye_solo_cambios_sin_aplicar_y_filtra_por_parte.
     */
    public function scopePropuestasPendientes($query, ?string $parte = null)
    {
        $query->where('data->tipo', 'cambio')
            ->whereNull('data->activated_by')
            ->whereIn('estado_id', [Estado::borrador()->id, Estado::validado()->id]);

        if ($parte === 'campos') {
            $query->whereJsonContainsKey('data->diff->campos');
        } elseif ($parte) {
            $query->whereJsonContainsKey("data->diff->relaciones->{$parte}");
        }

        return $query;
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
            $this->update([
                'estado_id' => Estado::validado()->id,
                'validado_por_id' => $usuario->id,
                'validado_en' => now(),
            ]);

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
                'aprobado_por_id' => $usuario->id,
                'aprobado_en' => now(),
                'data' => array_merge($this->data ?? [], ['activated_by' => $usuario->name]),
            ]);
            $this->fresh()->aplicarCambios();
        });
    }

    public function marcarRechazada(User $usuario): void
    {
        $this->update([
            'estado_id' => Estado::borrado()->id,
            'rechazado_por_id' => $usuario->id,
            'rechazado_en' => now(),
        ]);
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
                static::aplicarOperacionesPorElemento($model, $relacion, $ops);
            }
        }
    }

    /**
     * Operaciones puntuales sobre un solo elemento de la relación, las que generan
     * las propuestas por elemento de riesgo/show (ver PropuestasEnBloque): 'agregar'
     * [id => pivot] asocia sin duplicar si ya estaba, y 'actualizar' [id => pivot]
     * cambia sólo el pivot (la mitigación). A diferencia de 'sync', no pisan al resto
     * del bloque, así dos propuestas sobre elementos distintos no se contradicen.
     * También la llama RiesgoController::aplicarCambiosActualizacion().
     * Tests: validar_una_propuesta_aplica_solo_su_elemento_y_rechazar_otra_no_toca_nada,
     * un_cambio_de_mitigacion_propuesto_solo_toca_el_pivot_de_ese_control.
     */
    public static function aplicarOperacionesPorElemento(Model $model, string $relacion, array $ops): void
    {
        if (isset($ops['agregar'])) {
            $model->$relacion()->syncWithoutDetaching($ops['agregar']);
        }
        foreach ($ops['actualizar'] ?? [] as $id => $pivot) {
            $model->$relacion()->updateExistingPivot($id, $pivot);
        }
    }
}
