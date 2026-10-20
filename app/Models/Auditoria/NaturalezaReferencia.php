<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un principio de referencia de una naturaleza ("Las empresas deben…", D-032). */
class NaturalezaReferencia extends Model
{
    protected $table = 'naturaleza_referencias';

    protected $fillable = ['naturaleza_id', 'texto', 'orden'];

    public function naturaleza(): BelongsTo
    {
        return $this->belongsTo(Naturaleza::class);
    }
}
