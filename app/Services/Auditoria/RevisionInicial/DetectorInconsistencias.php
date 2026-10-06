<?php

namespace App\Services\Auditoria\RevisionInicial;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Catálogo de inconsistencias de la revisión de la carga inicial (D-024). Un
 * "error" bloquea la firma hasta arreglarse; una "advertencia" se firma igual,
 * con comentario; lo que ya tiene un arreglo validado sale "en curso" (ver
 * detectar()). Trabaja sobre elementos cargados con consulta(), que suma los
 * conteos que necesita cada regla, para revisar listados enteros sin N+1. Lo
 * consume el componente RevisionInicial\Index, que también decide si se puede
 * firmar.
 * Tests: DetectorInconsistenciasTest.
 */
class DetectorInconsistencias
{
    public const ERROR = 'error';

    public const ADVERTENCIA = 'advertencia';

    /** Resuelta por una propuesta validada que espera al comité: no bloquea. */
    public const EN_CURSO = 'en_curso';

    /** Consulta base del tipo de elemento con los conteos que usa detectar(). */
    public static function consulta(string $clase): Builder
    {
        return match ($clase) {
            Riesgo::class => Riesgo::query()->withCount(['areas', 'objetivos', 'planesAccion', 'controles']),
            Control::class => Control::query()->withCount([
                'riesgos',
                'riesgos as riesgos_sin_mitigacion_count' => fn ($q) => $q->where('control_riesgo.mitigacion', 0),
            ]),
            Objetivo::class => Objetivo::query()->withCount(['riesgos', 'peisItems']),
            PlanAccion::class => PlanAccion::query()->withCount(['riesgos', 'tareas']),
            Tarea::class => Tarea::query()->withCount('planesAccion'),
        };
    }

    /**
     * Relaciones que cuentan las reglas, con su inversa del otro lado: una
     * propuesta de un plan que agrega una tarea también arregla la tarea.
     */
    private const RELACIONES = [
        Riesgo::class => [
            'objetivos' => [Objetivo::class, 'riesgos'],
            'planesAccion' => [PlanAccion::class, 'riesgos'],
            'controles' => [Control::class, 'riesgos'],
            'areas' => null,
        ],
        Control::class => ['riesgos' => [Riesgo::class, 'controles']],
        Objetivo::class => ['riesgos' => [Riesgo::class, 'objetivos'], 'peisItems' => null],
        PlanAccion::class => ['riesgos' => [Riesgo::class, 'planesAccion'], 'tareas' => [Tarea::class, 'planesAccion']],
        Tarea::class => ['planesAccion' => [PlanAccion::class, 'tareas']],
    ];

    /** @var array{propias: array, inversas: array}|null propuestas validadas pendientes, indexadas */
    private ?array $propuestas = null;

    /**
     * Con $contarBorradores proyecta también las propuestas en borrador, como si
     * el gerente ya las hubiera validado: es la vista previa de "Validar y firmar".
     */
    public function __construct(private bool $contarBorradores = false) {}

    /**
     * Inconsistencias de un elemento cargado con consulta(). Si sus propuestas
     * ya validadas que esperan al comité la resuelven, la inconsistencia sale
     * con severidad EN_CURSO: se muestra, pero no bloquea ni pide comentario.
     * Las propuestas en borrador no cuentan hasta que el gerente las valida.
     * Tests: un_error_con_arreglo_validado_queda_en_curso_y_no_bloquea,
     * una_propuesta_en_borrador_no_cuenta_como_arreglo.
     *
     * @return list<array{codigo: string, severidad: string, mensaje: string}>
     */
    public function detectar(Model $elemento): array
    {
        $actuales = $this->reglas($elemento);
        $proyectado = $this->proyectar($elemento);

        if ($proyectado === null || $actuales === []) {
            return $actuales;
        }

        $siguen = collect($this->reglas($proyectado))->pluck('codigo');

        return array_map(fn ($h) => $siguen->contains($h['codigo']) ? $h : [
            ...$h,
            'severidad' => self::EN_CURSO,
            'mensaje' => $h['mensaje'].' Tiene un arreglo validado que espera al comité.',
        ], $actuales);
    }

    /**
     * Copia del elemento como quedaría al aplicar sus propuestas validadas
     * pendientes: campos propuestos y conteos de relaciones recalculados con las
     * operaciones de las propuestas propias y de las del otro lado de cada
     * relación. Null si no tiene ninguna que lo toque.
     */
    private function proyectar(Model $elemento): ?Model
    {
        $this->propuestas ??= $this->cargarPropuestas();
        $clave = $elemento->getMorphClass().'#'.$elemento->getKey();
        $propias = $this->propuestas['propias'][$clave] ?? [];
        $inversas = $this->propuestas['inversas'][$clave] ?? [];

        if ($propias === [] && $inversas === []) {
            return null;
        }

        $proyectado = clone $elemento;

        foreach ($propias as $data) {
            $proyectado->forceFill($data['campos'] ?? []);
        }

        foreach (self::RELACIONES[$elemento::class] as $relacion => $inversa) {
            $propiasDeRelacion = array_filter(array_map(fn ($d) => $d['relaciones'][$relacion] ?? null, $propias));
            $inversasDeRelacion = $inversas[$relacion] ?? [];

            if ($propiasDeRelacion === [] && $inversasDeRelacion === []) {
                continue;
            }

            $ids = collect($elemento->$relacion()->allRelatedIds())->map(fn ($id) => (int) $id);

            foreach ($propiasDeRelacion as $ops) {
                if (isset($ops['sync'])) {
                    $ids = collect(self::ids($ops['sync']));
                }
                $ids = $ids->merge(self::ids($ops['attach'] ?? []))->merge(self::ids($ops['agregar'] ?? []))
                    ->diff(self::ids($ops['detach'] ?? []));
            }
            foreach ($inversasDeRelacion as [$id, $agrega]) {
                $ids = $agrega ? $ids->push($id) : $ids->reject(fn ($otro) => $otro === $id);
            }

            $proyectado->setAttribute(Str::snake($relacion).'_count', $ids->unique()->count());
        }

        return $proyectado;
    }

    /**
     * Una sola consulta con todas las propuestas validadas que esperan al
     * comité, indexadas por elemento ('propias') y por el elemento del otro lado
     * de cada relación que tocan ('inversas', [id del origen, agrega?]).
     */
    private function cargarPropuestas(): array
    {
        $indice = ['propias' => [], 'inversas' => []];

        $pendientes = Actualizacion::query()->propuestasPendientes()
            ->whereIn('estado_id', array_filter([Estado::validado()?->id, $this->contarBorradores ? Estado::borrador()?->id : null]))
            ->orderBy('created_at')
            ->get(['id', 'actualizable_type', 'actualizable_id', 'data']);

        foreach ($pendientes as $propuesta) {
            $data = $propuesta->data;
            $indice['propias'][$propuesta->actualizable_type.'#'.$propuesta->actualizable_id][] = $data;

            foreach ($data['relaciones'] ?? [] as $relacion => $ops) {
                [$claseInversa, $relacionInversa] = (self::RELACIONES[$propuesta->actualizable_type] ?? [])[$relacion] ?? [null, null];
                if (! $claseInversa) {
                    continue;
                }
                $origen = (int) $propuesta->actualizable_id;
                $agregados = [...self::ids($ops['sync'] ?? []), ...self::ids($ops['attach'] ?? []), ...self::ids($ops['agregar'] ?? [])];
                foreach ($agregados as $id) {
                    $indice['inversas'][(new $claseInversa)->getMorphClass().'#'.$id][$relacionInversa][] = [$origen, true];
                }
                foreach (self::ids($ops['detach'] ?? []) as $id) {
                    $indice['inversas'][(new $claseInversa)->getMorphClass().'#'.$id][$relacionInversa][] = [$origen, false];
                }
            }
        }

        return $indice;
    }

    /** Ids de una operación: lista de ids (sync, detach) o [id => pivot] (agregar). */
    private static function ids(array $ops): array
    {
        return array_map('intval', array_is_list($ops) ? $ops : array_keys($ops));
    }

    private function reglas(Model $elemento): array
    {
        $sinArea = $elemento instanceof Riesgo ? $elemento->areas_count === 0 : ! $elemento->area_id;

        $hallazgos = [
            ...($sinArea ? [$this->error('sin_area', 'No tiene área asignada: ninguna gerencia puede firmarlo.')] : []),
            ...(! $elemento->user_id ? [$this->advertencia('sin_responsable', 'No tiene responsable.')] : []),
        ];

        return [...$hallazgos, ...match (true) {
            $elemento instanceof Riesgo => $this->riesgo($elemento),
            $elemento instanceof Control => $this->control($elemento),
            $elemento instanceof Objetivo => $this->objetivo($elemento),
            $elemento instanceof PlanAccion => $this->plan($elemento),
            $elemento instanceof Tarea => $this->tarea($elemento),
        }];
    }

    /** True si alguna inconsistencia es un error, que bloquea la firma. */
    public static function tieneErrores(array $hallazgos): bool
    {
        return collect($hallazgos)->contains('severidad', self::ERROR);
    }

    private function riesgo(Riesgo $riesgo): array
    {
        $hallazgos = [];

        if ($riesgo->objetivos_count === 0) {
            $hallazgos[] = $this->error('sin_objetivo', 'No tiene objetivos asociados.');
        }
        if ($riesgo->respuesta === RespuestaRiesgo::Mitigar && $riesgo->planes_accion_count === 0) {
            $hallazgos[] = $this->error('mitigar_sin_plan', 'La respuesta es Reducir/Mitigar pero no tiene plan de acción.');
        }
        if ($riesgo->respuesta?->exigeFundamento() && blank($riesgo->fundamento)) {
            $hallazgos[] = $this->error('sin_fundamento', "La respuesta {$riesgo->respuesta->label()} exige fundamento y no lo tiene.");
        }
        if ((int) $riesgo->impacto === 0 || (int) $riesgo->probabilidad === 0) {
            $hallazgos[] = $this->advertencia('valor_en_cero', 'El impacto o la probabilidad están en 0.');
        }
        if ($riesgo->controles_count === 0) {
            $hallazgos[] = $this->advertencia('sin_controles', 'No tiene controles asociados.');
        }

        return $hallazgos;
    }

    private function control(Control $control): array
    {
        $hallazgos = [];

        if ((int) $control->mitigacion_default === 0) {
            $hallazgos[] = $this->advertencia('mitigacion_en_cero', 'La mitigación por defecto está en 0.');
        }
        if ($control->riesgos_sin_mitigacion_count > 0) {
            $hallazgos[] = $this->advertencia('asociacion_en_cero', "Mitiga 0 en {$control->riesgos_sin_mitigacion_count} de sus riesgos.");
        }
        if ($control->riesgos_count === 0) {
            $hallazgos[] = $this->advertencia('sin_riesgos', 'No mitiga ningún riesgo.');
        }
        if ($control->pausado) {
            $hallazgos[] = $this->advertencia('pausado', 'Está pausado: no mitiga.');
        }

        return $hallazgos;
    }

    private function objetivo(Objetivo $objetivo): array
    {
        $hallazgos = [];

        if ($objetivo->peis && $objetivo->peis_items_count === 0) {
            $hallazgos[] = $this->error('peis_sin_items', 'Es PEIS pero no tiene ítems.');
        }
        if ($objetivo->riesgos_count === 0) {
            $hallazgos[] = $this->advertencia('sin_riesgos', 'No tiene riesgos asociados.');
        }

        return $hallazgos;
    }

    private function plan(PlanAccion $plan): array
    {
        $hallazgos = [];

        if ($plan->riesgos_count === 0) {
            $hallazgos[] = $this->advertencia('sin_riesgos', 'No tiene riesgos asociados.');
        }
        if ($plan->tareas_count === 0) {
            $hallazgos[] = $this->advertencia('sin_tareas', 'No tiene tareas.');
        }

        return $hallazgos;
    }

    private function tarea(Tarea $tarea): array
    {
        $hallazgos = [];

        if ($tarea->planes_accion_count === 0) {
            $hallazgos[] = $this->error('sin_plan', 'No pertenece a ningún plan de acción.');
        }
        if (! $tarea->fecha) {
            $hallazgos[] = $this->advertencia('sin_fecha', 'No tiene fecha.');
        } elseif ($tarea->fecha->isPast() && ! $tarea->fecha->isToday() && $tarea->porcentaje_avance < 100) {
            $hallazgos[] = $this->advertencia('vencida', "Venció el {$tarea->fecha->format('d/m/Y')} con {$tarea->porcentaje_avance}% de avance.");
        }

        return $hallazgos;
    }

    private function error(string $codigo, string $mensaje): array
    {
        return ['codigo' => $codigo, 'severidad' => self::ERROR, 'mensaje' => $mensaje];
    }

    private function advertencia(string $codigo, string $mensaje): array
    {
        return ['codigo' => $codigo, 'severidad' => self::ADVERTENCIA, 'mensaje' => $mensaje];
    }
}
