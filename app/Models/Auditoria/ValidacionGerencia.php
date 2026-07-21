<?php

namespace App\Models\Auditoria;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Voto de una gerencia sobre una Actualizacion de un riesgo compartido entre
 * varias gerencias (doble validación). `aprueba` = true (validar) / false
 * (rechazar). Ver Actualizacion::registrarVoto()/todasLasGerenciasValidaron().
 */
class ValidacionGerencia extends Model
{
    protected $table = 'validacion_gerencia';

    protected $fillable = [
        'actualizacion_id',
        'area_id',
        'user_id',
        'aprueba',
    ];

    protected $casts = [
        'aprueba' => 'boolean',
    ];

    public function actualizacion(): BelongsTo
    {
        return $this->belongsTo(Actualizacion::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
