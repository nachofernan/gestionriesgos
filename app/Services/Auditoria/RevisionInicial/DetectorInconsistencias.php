<?php

namespace App\Services\Auditoria\RevisionInicial;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de inconsistencias de la revisión de la carga inicial (D-024). Un
 * "error" bloquea la firma hasta arreglarse; una "advertencia" se firma igual,
 * con comentario. Trabaja sobre elementos cargados con consulta(), que suma los
 * conteos que necesita cada regla, para revisar listados enteros sin N+1. Lo
 * consume el componente RevisionInicial\Index, que también decide si se puede
 * firmar.
 * Tests: DetectorInconsistenciasTest.
 */
class DetectorInconsistencias
{
    public const ERROR = 'error';

    public const ADVERTENCIA = 'advertencia';

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
     * Inconsistencias de un elemento cargado con consulta().
     *
     * @return list<array{codigo: string, severidad: string, mensaje: string}>
     */
    public function detectar(Model $elemento): array
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
