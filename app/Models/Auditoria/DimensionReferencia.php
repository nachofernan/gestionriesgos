<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un principio de referencia de una dimensión ("Las empresas deben…", D-032). */
class DimensionReferencia extends Model
{
    protected $table = 'dimension_referencias';

    protected $fillable = ['dimension_id', 'texto', 'orden'];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }
}
