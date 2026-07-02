<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Estado de ciclo de vida compartido por Riesgo, Control, Objetivo, PlanAccion,
 * Tarea y Actualizacion: borrador → validado → activo, o borrador/validado →
 * borrado. Los helpers estáticos (borrador(), validado(), etc.) son la forma
 * estándar de resolver el ID de un estado por nombre en todo el módulo.
 */
class Estado extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'estados';

    protected $fillable = ['nombre', 'color'];

    public function riesgos(): HasMany
    {
        return $this->hasMany(Riesgo::class, 'estado_id');
    }

    public function controles(): HasMany
    {
        return $this->hasMany(Control::class, 'estado_id');
    }

    public function objetivos(): HasMany
    {
        return $this->hasMany(Objetivo::class, 'estado_id');
    }

    public function planesAccion(): HasMany
    {
        return $this->hasMany(PlanAccion::class, 'estado_id');
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'estado_id');
    }

    public function actualizaciones(): HasMany
    {
        return $this->hasMany(Actualizacion::class, 'estado_id');
    }

    public static function borrador(): ?self
    {
        return static::where('nombre', 'borrador')->first();
    }

    public static function validado(): ?self
    {
        return static::where('nombre', 'validado')->first();
    }

    public static function activo(): ?self
    {
        return static::where('nombre', 'activo')->first();
    }

    public static function borrado(): ?self
    {
        return static::where('nombre', 'borrado')->first();
    }
}
