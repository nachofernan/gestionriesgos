<?php

namespace App\Services\Auditoria;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\Testeo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Lo que la actividad de un elemento muestra además de su propio historial (D-056):
 * se deriva al leer, sin crear Actualizaciones. Un cambio de relación se registra
 * en un solo lado (la asociación riesgo–objetivo vive en el historial del riesgo,
 * la de plan–tarea en el del plan); acá se traen esas entradas para la contraparte,
 * de solo lectura, sólo si quien mira puede ver el elemento donde se registraron.
 * Además, los testeos de evidencia (D-040) de un control o de un riesgo, también
 * los que confirman el %, que no dejan Actualizacion.
 * Lo consume GestionActualizaciones (variante 'timeline').
 * Tests: ActividadRelacionadaTest.
 */
class ActividadRelacionada
{
    /**
     * [clase de la entidad => [[clase donde se registra la relación, parte del diff]]].
     * Un objetivo recibe de dos lados: sus riesgos y, si es PEIS, los objetivos que le
     * contribuyen (la contribución se registra en el que contribuye).
     */
    private const ORIGENES = [
        Objetivo::class => [[Riesgo::class, 'objetivos'], [Objetivo::class, 'peis']],
        Control::class => [[Riesgo::class, 'controles']],
        PlanAccion::class => [[Riesgo::class, 'planesAccion']],
        Tarea::class => [[PlanAccion::class, 'tareas']],
    ];

    /**
     * Entradas de otros elementos cuyo diff de relaciones menciona a $entidad. Se
     * dejan afuera las que $user no puede ver, la eliminación de un plan (la tarea
     * ya tiene su propia entrada «Eliminada junto con el plan») y, en un control, los
     * testeos (los cuenta testeos(), con comentario y adjuntos).
     * Tests: asociar_un_riesgo_a_un_objetivo_aparece_en_la_actividad_del_objetivo,
     * no_aparece_lo_que_viene_de_un_riesgo_que_el_usuario_no_puede_ver,
     * eliminar_un_plan_no_duplica_la_entrada_en_sus_tareas.
     */
    public static function entradas(Model $entidad, User $user, array $with = []): Collection
    {
        return collect(self::ORIGENES[$entidad::class] ?? [])
            ->flatMap(fn ($origen) => self::entradasDesde($entidad, $user, $with, ...$origen))
            ->values();
    }

    private static function entradasDesde(Model $entidad, User $user, array $with, string $claseOrigen, string $parte): Collection
    {
        return Actualizacion::query()
            ->where('actualizable_type', (new $claseOrigen)->getMorphClass())
            ->whereJsonContainsKey("data->diff->relaciones->{$parte}")
            ->with([...$with, 'actualizable.estado'])
            ->get()
            ->filter(function (Actualizacion $a) use ($entidad, $parte, $user) {
                $data = $a->data ?? [];
                if (! empty($data['eliminacion']) || ($data['origen'] ?? null) === 'testeo') {
                    return false;
                }
                $menciona = collect($data['diff']['relaciones'][$parte] ?? [])
                    ->flatten(1)
                    ->contains(fn ($item) => (int) ($item['id'] ?? 0) === $entidad->id);

                return $menciona && $a->actualizable && $user->can('view', $a->actualizable);
            })
            ->values();
    }

    /**
     * Testeos de un control (de todas sus relaciones) o de un riesgo (de todos sus
     * controles), del más nuevo al más viejo, sólo de relaciones cuya contraparte
     * $user puede ver. En el riesgo se omite el que ya dejó su Actualizacion
     * (`data.testeo_id`, el que movió el %), para no contarlo dos veces;
     * $idsYaRegistrados son esos ids.
     * Tests: un_testeo_que_confirma_el_porcentaje_aparece_en_el_control_y_en_el_riesgo,
     * un_testeo_que_cambia_el_porcentaje_aparece_una_sola_vez_en_cada_lado.
     */
    public static function testeos(Model $entidad, User $user, array $idsYaRegistrados = []): Collection
    {
        $columna = match (true) {
            $entidad instanceof Control => 'control_id',
            $entidad instanceof Riesgo => 'riesgo_id',
            default => null,
        };
        if (! $columna) {
            return collect();
        }
        $contraparte = $columna === 'control_id' ? 'riesgo' : 'control';

        return Testeo::query()
            ->where($columna, $entidad->id)
            ->whereNotIn('id', $idsYaRegistrados)
            ->with(['user.area', 'media', "{$contraparte}.estado"])
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->filter(fn (Testeo $t) => $t->$contraparte && $user->can('view', $t->$contraparte))
            ->values();
    }

    /** Ruta de la pantalla del elemento donde se registró una entrada derivada. */
    public static function urlDe(Model $origen): string
    {
        return match (true) {
            $origen instanceof Riesgo => route('auditoria.riesgos.show', $origen->id),
            $origen instanceof PlanAccion => route('auditoria.planes.show', $origen->id),
            $origen instanceof Objetivo => route('auditoria.objetivos.show', $origen->id),
        };
    }

    /** Cómo se nombra en pantalla el elemento donde se registró: 'riesgo' | 'plan' | 'objetivo'. */
    public static function tipoDe(Model $origen): string
    {
        return match (true) {
            $origen instanceof PlanAccion => 'plan',
            $origen instanceof Objetivo => 'objetivo',
            default => 'riesgo',
        };
    }
}
