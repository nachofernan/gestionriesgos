<?php

namespace App\Models\Auditoria;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Estado de ciclo de vida compartido por Riesgo, Control, Objetivo, PlanAccion,
 * Tarea y Actualizacion: borrador → validado → aprobado, o borrador/validado →
 * borrado. Los helpers estáticos (borrador(), validado(), etc.) son la forma
 * estándar de resolver el ID de un estado por nombre en todo el módulo.
 */
class Estado extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'estados';

    protected $fillable = ['nombre', 'color'];

    /**
     * Orden canónico de estados para listados en toda la app: aprobado primero,
     * luego validado, borrador y por último borrado. Es puramente de presentación
     * (no afecta el ciclo de negocio en Riesgo::cambioRequiereDobleValidacion() ni
     * accessors de valor), por eso vive acá y no en el núcleo sagrado.
     */
    public const ORDEN = [
        'aprobado' => 1,
        'validado' => 2,
        'borrador' => 3,
        'borrado' => 4,
    ];

    /**
     * Fragmento SQL para ORDER BY sobre una columna de nombre de estado (por
     * defecto 'estados.nombre' tras un join). Usarlo como primer criterio de
     * cualquier orderByRaw/orderBy para que el estado mande sobre el resto.
     */
    public static function ordenSql(string $columna = 'estados.nombre'): string
    {
        $casos = collect(self::ORDEN)
            ->map(fn (int $peso, string $nombre) => "WHEN '{$nombre}' THEN {$peso}")
            ->implode(' ');

        return "CASE {$columna} {$casos} ELSE 99 END";
    }

    /** Catálogo completo de estados en el orden canónico, para poblar filtros (checkboxes, selects). */
    public static function todosOrdenados()
    {
        return self::all()->sortBy(fn (self $e) => self::ORDEN[$e->nombre] ?? 99)->values();
    }

    /**
     * Peso numérico del estado de un modelo ya cargado en memoria, para ordenar
     * colecciones/relaciones con sortBy() en controladores y vistas Blade.
     */
    public static function peso(?self $estado): int
    {
        return self::ORDEN[$estado?->nombre] ?? 99;
    }

    /**
     * Reordena una colección ya cargada de modelos con relación `estado` (aprobado
     * primero, borrado al final). Para relaciones belongsToMany con datos de pivot
     * (Control::riesgos, PlanAccion::riesgos, etc.) es más seguro que ordenar por
     * SQL: evitar el join adicional que pisaría el select() y perdería el pivot.
     *
     * @param  Collection|\Illuminate\Database\Eloquent\Collection  $coleccion
     */
    public static function ordenarColeccion($coleccion)
    {
        return $coleccion->sortBy(fn ($item) => self::peso($item->estado))->values();
    }

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

    public static function aprobado(): ?self
    {
        return static::where('nombre', 'aprobado')->first();
    }

    public static function borrado(): ?self
    {
        return static::where('nombre', 'borrado')->first();
    }
}
