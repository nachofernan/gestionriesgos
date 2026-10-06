<?php

namespace App\Models\Auditoria;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

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
        'comentario',
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
