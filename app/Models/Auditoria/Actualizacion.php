<?php

namespace App\Models\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
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
        'motivo_rechazo',
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
     * ActualizacionPolicy). La usan GestionActualizaciones y Conversacion.
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
     * al rol (estadoInicialParaCambio()); se aplica en el acto si nace aprobado,
     * o validado sobre una entidad validada.
     * Guarda el diff (antes → después) que pintan el historial y las tarjetas de
     * propuesta. Sale de GestionActualizaciones::guardar() para que también lo use
     * FichaRiesgo. Quien llama ya validó los campos y autorizó 'update'.
     * $propagarMitigacion (sólo Control, cambiando mitigacion_default) marca la
     * propuesta con 'propagar_mitigacion': cuando se aplique, el nuevo default se
     * lleva a todos los riesgos asociados (Control::propagarMitigacionDefault()).
     * Tests: un_empleado_edita_la_ficha_y_queda_propuesta_solo_con_los_campos_que_cambiaron,
     * como_propuesta_no_propaga_hasta_que_se_aplica.
     */
    public static function registrarCambioCampos(Model $entidad, User $usuario, string $mensaje, array $campos, bool $propagarMitigacion = false): self
    {
        $estadoEntidad = $entidad->estado?->nombre;
        $estadoId = static::estadoInicialParaCambio($usuario, $estadoEntidad);

        $diff = [];
        foreach ($campos as $campo => $nuevo) {
            $antes = $entidad->$campo;
            // respuesta castea a RespuestaRiesgo (BackedEnum): sin esto, comparar
            // el enum contra el string crudo del select nunca da igual y el diff
            // mostraría "cambio" aunque se reeligiera el mismo valor.
            if ($antes instanceof \BackedEnum) {
                $antes = $antes->value;
            }
            // Las fechas casteadas (fecha_objetivo, fecha) llegan como Carbon: se
            // comparan y guardan en el mismo formato que manda el input date.
            if ($antes instanceof \DateTimeInterface) {
                $antes = $antes->format('Y-m-d');
            }
            if ($antes != $nuevo) {
                $diff[$campo] = ['antes' => $antes, 'despues' => $nuevo];
            }
        }

        $data = ['tipo' => 'cambio', 'campos' => $campos];
        if (! empty($diff)) {
            $data['diff'] = ['campos' => $diff];
        }
        if ($propagarMitigacion && $entidad instanceof Control && isset($diff['mitigacion_default'])) {
            $data['propagar_mitigacion'] = true;
        }

        $aplicar = static::seAplicaAlRegistrar($estadoId, $estadoEntidad);
        if ($aplicar) {
            $data['activated_by'] = $usuario->name;
        }

        return DB::transaction(function () use ($entidad, $usuario, $mensaje, $campos, $estadoId, $data, $aplicar) {
            $actualizacion = $entidad->actualizaciones()->create([
                'user_id' => $usuario->id,
                'mensaje' => $mensaje,
                'estado_id' => $estadoId,
                'data' => $data,
            ]);

            if ($aplicar) {
                $antes = $entidad->mitigacion_default;
                $entidad->update($campos);
                if ($data['propagar_mitigacion'] ?? false) {
                    $entidad->propagarMitigacionDefault((int) $antes, $usuario);
                }
            }

            return $actualizacion;
        });
    }

    /**
     * Registra el reemplazo completo de una relación de la entidad (hoy, las sedes
     * del riesgo desde su ficha). Misma regla de estado inicial y de aplicación
     * que registrarCambioCampos(): si se aplica en el acto hace el sync; si no,
     * queda como propuesta y aplicarCambios() hace el sync al validar o aprobar.
     * $diff es ['agrega' => [[id, nombre]], 'quita' => [...]], para el historial.
     * $campos son campos que viajan atados a la relación en la misma propuesta (el
     * fundamento de las naturalezas, D-032): se validan o rechazan juntos.
     * Quien llama ya validó los ids y autorizó 'proponer'.
     * Tests: un_empleado_propone_cambiar_las_sedes_y_se_aplica_al_validar,
     * un_gerente_cambia_las_sedes_de_un_riesgo_validado_en_el_acto,
     * un_empleado_propone_naturalezas_con_fundamento_y_se_aplican_juntos_al_validar.
     */
    public static function registrarCambioSync(Model $entidad, User $usuario, string $mensaje, string $relacion, array $ids, array $diff, array $campos = []): self
    {
        $estadoId = static::estadoInicialParaCambio($usuario, $entidad->estado?->nombre);
        $aplicar = static::seAplicaAlRegistrar($estadoId, $entidad->estado?->nombre);

        $data = [
            'tipo' => 'cambio',
            'relaciones' => [$relacion => ['sync' => $ids]],
            'diff' => ['relaciones' => [$relacion => $diff]],
        ];
        if (! empty($campos)) {
            $data['campos'] = $campos;
            $data['diff']['campos'] = collect($campos)
                ->map(fn ($nuevo, $campo) => ['antes' => $entidad->$campo, 'despues' => $nuevo])
                ->all();
        }
        if ($aplicar) {
            $data['activated_by'] = $usuario->name;
        }

        return DB::transaction(function () use ($entidad, $usuario, $mensaje, $relacion, $ids, $campos, $estadoId, $data, $aplicar) {
            $actualizacion = $entidad->actualizaciones()->create([
                'user_id' => $usuario->id,
                'mensaje' => $mensaje,
                'estado_id' => $estadoId,
                'data' => $data,
            ]);

            if ($aplicar) {
                $entidad->$relacion()->sync($ids);
                if (! empty($campos)) {
                    $entidad->update($campos);
                }
            }

            return $actualizacion;
        });
    }

    /**
     * Si un cambio recién registrado se aplica en el acto: nace aprobado, o nace
     * validado sobre una entidad que ya está validada.
     */
    private static function seAplicaAlRegistrar(int $estadoId, ?string $estadoEntidad): bool
    {
        return $estadoId === Estado::aprobado()->id
            || ($estadoId === Estado::validado()->id && $estadoEntidad === 'validado');
    }

    /**
     * Propuestas de cambio que todavía no se aplicaron a su entidad: tipo
     * "cambio", en borrador (espera validación) o validada sin `activated_by`
     * (espera al comité). Con `$parte` se acota a las que tocan esa parte de la
     * entidad: una relación ('objetivos', 'controles', 'planesAccion', 'tareas', 'sedes') o
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

    /**
     * El diff de `data` en texto listo para pintar: 'campos' = filas
     * [campo, etiqueta, antes, despues] con los valores ya formateados (tipo de
     * riesgo por nombre, respuesta por label, fechas d/m/Y, avance en %), y
     * 'relaciones' = [parte => [etiqueta, items: [op, nombre, detalle]]] con op
     * agrega/quita/cambia. Lo consumen x-auditoria.propuesta-pendiente (sólo
     * 'campos') y el modal de Pendientes, para que las dos pantallas no se
     * desincronicen en etiquetas ni formato.
     */
    public function diffLegible(): array
    {
        $diff = $this->data['diff'] ?? [];

        $tiposRiesgo = [];
        if (isset($diff['campos']['tipo_riesgo_id'])) {
            $tiposRiesgo = TipoRiesgo::whereIn('id', array_filter($diff['campos']['tipo_riesgo_id']))->pluck('nombre', 'id');
        }

        $etiquetas = [
            'nombre' => 'Nombre', 'descripcion' => 'Descripción', 'respuesta' => 'Respuesta',
            'fundamento' => 'Fundamento', 'fundamento_naturaleza' => 'Fundamento de naturaleza', 'tipo_riesgo_id' => 'Tipo de riesgo',
            'impacto' => 'Impacto', 'probabilidad' => 'Probabilidad',
            'mitigacion_default' => 'Mitigación', 'pausado' => 'Pausado', 'fecha_objetivo' => 'Fecha objetivo',
            'fecha' => 'Fecha límite', 'porcentaje_avance' => 'Avance',
        ];
        $formatear = function ($campo, $valor) use ($tiposRiesgo) {
            if ($valor === null || $valor === '') {
                return '—';
            }

            return (string) match ($campo) {
                'tipo_riesgo_id' => $tiposRiesgo[$valor] ?? '#'.$valor,
                'respuesta' => RespuestaRiesgo::tryFrom($valor)?->label() ?? $valor,
                'fecha_objetivo', 'fecha' => Carbon::parse($valor)->format('d/m/Y'),
                'porcentaje_avance' => $valor.'%',
                default => is_bool($valor) ? ($valor ? 'Sí' : 'No') : $valor,
            };
        };

        $campos = [];
        foreach ($diff['campos'] ?? [] as $campo => $cambio) {
            $campos[] = [
                'campo' => $campo,
                'etiqueta' => $etiquetas[$campo] ?? ucfirst(str_replace('_', ' ', $campo)),
                'antes' => $formatear($campo, $cambio['antes'] ?? null),
                'despues' => $formatear($campo, $cambio['despues'] ?? null),
            ];
        }

        $partes = [
            'objetivos' => 'Objetivos', 'controles' => 'Controles', 'planesAccion' => 'Planes de acción',
            'tareas' => 'Tareas', 'sedes' => 'Sedes', 'naturalezas' => 'Naturalezas',
        ];
        $relaciones = [];
        foreach ($diff['relaciones'] ?? [] as $parte => $ops) {
            $items = [];
            foreach (['agrega', 'quita', 'cambia'] as $op) {
                foreach ($ops[$op] ?? [] as $elemento) {
                    $detalle = match (true) {
                        $op === 'agrega' && isset($elemento['mitigacion']) => 'mit. '.$elemento['mitigacion'],
                        $op === 'cambia' => 'mit. '.($elemento['mitigacion_antes'] ?? '—').' → '.($elemento['mitigacion_despues'] ?? '—'),
                        default => null,
                    };
                    $items[] = ['op' => $op, 'nombre' => $elemento['nombre'] ?? '—', 'detalle' => $detalle];
                }
            }
            if ($items) {
                $relaciones[$parte] = ['etiqueta' => $partes[$parte] ?? ucfirst($parte), 'items' => $items];
            }
        }

        return ['campos' => $campos, 'relaciones' => $relaciones];
    }

    /**
     * Valida la actualización y, si la entidad relacionada ya estaba en
     * "validado", aplica los cambios en el mismo paso (no espera una
     * aprobación aparte). Por acá pasa todo "validar" de una propuesta
     * (GestionActualizaciones y "Validar y firmar" de la revisión inicial);
     * quien llama ya autorizó con ActualizacionPolicy::validar().
     * Tests: validar_y_firmar_valida_los_cambios_y_firma,
     * una_propuesta_sobre_un_riesgo_validado_se_aplica_con_la_validacion_de_su_gerente.
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
                $this->fresh()->aplicarCambios($usuario);
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
            $this->fresh()->aplicarCambios($usuario);
        });
    }

    /**
     * Rechaza la propuesta con su motivo, que es obligatorio (D-031): quien llama ya
     * lo validó y autorizó con ActualizacionPolicy::rechazar().
     * Test: rechazar_una_propuesta_exige_motivo_y_lo_guarda.
     */
    public function marcarRechazada(User $usuario, string $motivo): void
    {
        $this->update([
            'estado_id' => Estado::borrado()->id,
            'rechazado_por_id' => $usuario->id,
            'rechazado_en' => now(),
            'motivo_rechazo' => $motivo,
        ]);
    }

    /**
     * Registro append-only del rechazo de un elemento (no de una propuesta): queda en
     * su historial como rechazo, con quién, cuándo y el motivo (D-031). Antes se
     * registraba en estado aprobado y la actividad lo pintaba como "Aplicada".
     * Lo usan los *Controller::rechazar() vía Concerns\RechazaElemento.
     */
    public static function registrarRechazo(Model $entidad, User $usuario, string $motivo): self
    {
        return $entidad->actualizaciones()->create([
            'user_id' => $usuario->id,
            'mensaje' => "Rechazado por {$usuario->name}: {$motivo}",
            'estado_id' => Estado::borrado()->id,
            'rechazado_por_id' => $usuario->id,
            'rechazado_en' => now(),
            'motivo_rechazo' => $motivo,
            'data' => ['tipo' => 'rechazo'],
        ]);
    }

    /**
     * Aplica sobre la entidad relacionada (`actualizable`) los cambios guardados en `data`:
     * actualiza los campos de `data['campos']` (o el objeto completo si viene en formato
     * legacy sin las claves `campos`/`relaciones`) y sincroniza las relaciones many-to-many
     * indicadas en `data['relaciones']` (sync/attach/detach). No hace nada si `data` está
     * vacío o si el tipo de actualización no es 'cambio'. Si la propuesta es de un
     * Control y lleva 'propagar_mitigacion', lleva el nuevo default a sus riesgos a
     * nombre de $usuario (quien la hace efectiva).
     * Tests: como_propuesta_no_propaga_hasta_que_se_aplica,
     * aprobar_el_control_aplica_la_propagacion_de_sus_actualizaciones_pendientes.
     */
    public function aplicarCambios(?User $usuario = null): void
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
        $mitigacionAntes = $model->mitigacion_default;

        if (isset($data['campos']) || isset($data['relaciones'])) {
            if (! empty($data['campos'])) {
                $model->update($data['campos']);
            }
            if (($data['propagar_mitigacion'] ?? false) && $model instanceof Control) {
                $model->propagarMitigacionDefault((int) $mitigacionAntes, $usuario ?? $this->user);
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
