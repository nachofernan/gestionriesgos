<?php

namespace App\Models\Auditoria;

use App\Models\User;
use App\Services\Auditoria\RevisionInicial\DetectorInconsistencias;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Firma de la revisión de la carga inicial (D-024) de un elemento ante una
 * gerencia: primero "revisado" (responsable o coordinador), después "firmado"
 * (gerente). Es un registro: no se deshace ni se anula si el elemento cambia.
 */
class RevisionInicial extends Model
{
    protected $table = 'revisiones_iniciales';

    protected $fillable = [
        'revisable_type',
        'revisable_id',
        'gerencia_id',
        'revisado_por_id',
        'revisado_en',
        'firmado_por_id',
        'firmado_en',
        'comentario_revision',
        'comentario_firma',
        'advertencias_aceptadas',
    ];

    protected $casts = [
        'revisado_en' => 'datetime',
        'firmado_en' => 'datetime',
        'advertencias_aceptadas' => 'array',
    ];

    public function revisable(): MorphTo
    {
        return $this->morphTo();
    }

    public function gerencia(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'gerencia_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

    public function firmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmado_por_id');
    }

    /**
     * Marca el elemento como revisado ante una gerencia. Quien llama ya
     * autorizó con RevisionInicialPolicy::revisar(). No se puede revisar lo que
     * ya está firmado.
     * Tests: el_coordinador_revisa_un_elemento_de_su_area_pero_no_lo_firma.
     */
    public static function registrarRevisado(Model $elemento, Area $gerencia, User $usuario, ?string $comentario = null): self
    {
        $revision = static::deElemento($elemento, $gerencia);

        if ($revision->firmado_en) {
            throw ValidationException::withMessages(['revision' => 'Ya está firmado.']);
        }

        $revision->fill([
            'revisado_por_id' => $usuario->id,
            'revisado_en' => now(),
            'comentario_revision' => filled($comentario) ? $comentario : $revision->comentario_revision,
        ])->save();

        return $revision;
    }

    /**
     * Firma del gerente. Quien llama ya autorizó con RevisionInicialPolicy::firmar().
     * Un error del detector bloquea la firma; las advertencias se aceptan con
     * comentario obligatorio y quedan registradas. No exige un "revisado"
     * previo: el gerente puede firmar aunque nadie de su equipo lo haya revisado.
     * Tests: no_se_firma_con_errores_abiertos, firmar_con_advertencias_exige_comentario.
     *
     * @param  list<array{codigo: string, severidad: string, mensaje: string}>  $hallazgos  de DetectorInconsistencias::detectar()
     */
    public static function registrarFirma(Model $elemento, Area $gerencia, User $usuario, array $hallazgos, ?string $comentario = null): self
    {
        if (DetectorInconsistencias::tieneErrores($hallazgos)) {
            throw ValidationException::withMessages(['revision' => 'Tiene errores sin resolver: no se puede firmar.']);
        }

        $advertencias = collect($hallazgos)->where('severidad', DetectorInconsistencias::ADVERTENCIA)->pluck('codigo')->values()->all();

        if ($advertencias && blank($comentario)) {
            throw ValidationException::withMessages(['comentario' => 'Para firmar con advertencias hace falta un comentario.']);
        }

        $revision = static::deElemento($elemento, $gerencia);

        if ($revision->firmado_en) {
            throw ValidationException::withMessages(['revision' => 'Ya está firmado.']);
        }

        $revision->fill([
            'firmado_por_id' => $usuario->id,
            'firmado_en' => now(),
            'comentario_firma' => filled($comentario) ? $comentario : null,
            'advertencias_aceptadas' => $advertencias ?: null,
        ])->save();

        return $revision;
    }

    /**
     * True si firmaron todas las gerencias del elemento (un riesgo compartido
     * necesita la firma de cada una). Requiere `revisionesIniciales` cargadas.
     * Tests: un_riesgo_compartido_queda_firmado_cuando_firman_todas_sus_gerencias.
     */
    public static function firmadoPorTodas(Model $elemento): bool
    {
        $gerencias = static::gerenciasDe($elemento);
        $firmadas = $elemento->revisionesIniciales->whereNotNull('firmado_en')->pluck('gerencia_id');

        return $gerencias->isNotEmpty() && $gerencias->every(fn (Area $g) => $firmadas->contains($g->id));
    }

    private static function deElemento(Model $elemento, Area $gerencia): self
    {
        return static::firstOrNew([
            'revisable_type' => $elemento->getMorphClass(),
            'revisable_id' => $elemento->getKey(),
            'gerencia_id' => $gerencia->id,
        ]);
    }

    /**
     * Gerencias que tienen que firmar un elemento: la de su área, o en un
     * riesgo la de cada una de sus áreas (area_riesgo), sin repetir. Vacía si
     * el elemento no tiene área, lo que el detector marca como error.
     *
     * @return Collection<int, Area>
     */
    public static function gerenciasDe(Model $elemento): Collection
    {
        $areas = $elemento instanceof Riesgo
            ? $elemento->areas
            : collect([$elemento->area])->filter();

        return $areas->map(fn (Area $area) => $area->gerencia())
            ->filter()
            ->unique('id')
            ->values();
    }
}
