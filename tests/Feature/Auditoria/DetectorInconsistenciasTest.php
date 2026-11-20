<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use App\Services\Auditoria\RevisionInicial\DetectorInconsistencias;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Catálogo de inconsistencias de la revisión de la carga inicial (D-024): cada
 * regla aparece cuando corresponde, con su severidad, y un elemento completo no
 * marca nada.
 */
class DetectorInconsistenciasTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private const EVALUACION_CLASICA = [
        'metodo' => 'clasica',
        'respuestas' => ['probabilidad' => [1, 1, 1, 1, 1], 'impacto' => [1, 1, 1, 1, 1, 0, 0, 0, 0, 0]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->area = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
    }

    /** Códigos detectados, con su severidad, recargando el elemento con sus conteos. */
    private function hallazgos(Model $elemento): array
    {
        $cargado = DetectorInconsistencias::consulta($elemento::class)->findOrFail($elemento->id);

        return collect((new DetectorInconsistencias)->detectar($cargado))
            ->mapWithKeys(fn ($h) => [$h['codigo'] => $h['severidad']])
            ->all();
    }

    private function riesgoCompleto(array $atributos = []): Riesgo
    {
        $riesgo = Riesgo::factory()->aprobado()->create([
            'area_id' => $this->area->id,
            'impacto' => 5,
            'probabilidad' => 5,
            'respuesta' => RespuestaRiesgo::Mitigar,
            'evaluacion' => self::EVALUACION_CLASICA,
            ...$atributos,
        ]);
        $riesgo->objetivos()->attach($this->objetivo()->id);
        $riesgo->planesAccion()->attach(PlanAccion::factory()->create(['area_id' => $this->area->id])->id);
        $riesgo->controles()->attach(Control::factory()->create(['area_id' => $this->area->id])->id);

        return $riesgo;
    }

    private function objetivo(array $atributos = []): Objetivo
    {
        return Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->area->id, 'user_id' => User::factory()->create()->id, ...$atributos]);
    }

    /** Propuesta de cambio pendiente sobre el elemento, en el estado dado. */
    private function proponer(Model $elemento, array $data, string $estado = 'validado'): void
    {
        $elemento->actualizaciones()->create([
            'user_id' => User::factory()->create()->id,
            'mensaje' => 'Propuesta',
            'estado_id' => Estado::where('nombre', $estado)->firstOrFail()->id,
            'data' => ['tipo' => 'cambio', ...$data],
        ]);
    }

    #[Test]
    public function un_error_con_arreglo_validado_queda_en_curso_y_no_bloquea(): void
    {
        $riesgo = $this->riesgoCompleto();
        $riesgo->objetivos()->detach();
        $this->proponer($riesgo, ['relaciones' => ['objetivos' => ['agregar' => [$this->objetivo()->id => []]]]]);

        $hallazgos = $this->hallazgos($riesgo);

        $this->assertSame(['sin_objetivo' => 'en_curso'], $hallazgos);
        $this->assertFalse(DetectorInconsistencias::tieneErrores([['severidad' => 'en_curso']]));
    }

    #[Test]
    public function una_propuesta_en_borrador_no_cuenta_como_arreglo(): void
    {
        $riesgo = $this->riesgoCompleto();
        $riesgo->objetivos()->detach();
        $this->proponer($riesgo, ['relaciones' => ['objetivos' => ['agregar' => [$this->objetivo()->id => []]]]], 'borrador');

        $this->assertSame(['sin_objetivo' => 'error'], $this->hallazgos($riesgo));
    }

    #[Test]
    public function una_propuesta_validada_que_no_arregla_ese_error_no_lo_saca(): void
    {
        $riesgo = $this->riesgoCompleto();
        $riesgo->objetivos()->detach();
        $this->proponer($riesgo, ['campos' => ['nombre' => 'Otro nombre']]);
        $this->proponer($riesgo, ['relaciones' => ['objetivos' => ['detach' => [999]]]]);

        $this->assertSame(['sin_objetivo' => 'error'], $this->hallazgos($riesgo));
    }

    #[Test]
    public function un_cambio_de_campo_validado_arregla_el_fundamento(): void
    {
        $riesgo = $this->riesgoCompleto(['respuesta' => RespuestaRiesgo::Aceptar, 'fundamento' => null]);
        $this->proponer($riesgo, ['campos' => ['fundamento' => 'Costo de mitigar mayor al impacto.']]);

        $this->assertSame(['sin_fundamento' => 'en_curso'], $this->hallazgos($riesgo));
    }

    #[Test]
    public function el_arreglo_puede_venir_del_otro_lado_de_la_relacion(): void
    {
        $tarea = Tarea::factory()->create(['area_id' => $this->area->id, 'fecha' => now()->addMonth()]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id]);
        $this->proponer($plan, ['relaciones' => ['tareas' => ['agregar' => [$tarea->id => []]]]]);

        $this->assertSame(['sin_plan' => 'en_curso'], $this->hallazgos($tarea));
    }

    #[Test]
    public function un_riesgo_completo_no_tiene_inconsistencias(): void
    {
        $this->assertSame([], $this->hallazgos($this->riesgoCompleto()));
    }

    #[Test]
    public function un_riesgo_sin_objetivo_es_un_error(): void
    {
        $riesgo = $this->riesgoCompleto();
        $riesgo->objetivos()->detach();

        $this->assertSame(['sin_objetivo' => 'error'], $this->hallazgos($riesgo));
    }

    #[Test]
    public function mitigar_sin_plan_es_un_error_pero_otra_respuesta_sin_plan_no(): void
    {
        $riesgo = $this->riesgoCompleto();
        $riesgo->planesAccion()->detach();

        $this->assertSame(['mitigar_sin_plan' => 'error'], $this->hallazgos($riesgo));

        $riesgo->update(['respuesta' => RespuestaRiesgo::Evitar]);
        $this->assertSame([], $this->hallazgos($riesgo));
    }

    #[Test]
    public function aceptar_o_compartir_sin_fundamento_es_un_error(): void
    {
        $sinFundamento = $this->riesgoCompleto(['respuesta' => RespuestaRiesgo::Aceptar, 'fundamento' => null]);
        $conFundamento = $this->riesgoCompleto(['respuesta' => RespuestaRiesgo::Compartir, 'fundamento' => 'Se transfiere al seguro.']);

        $this->assertSame(['sin_fundamento' => 'error'], $this->hallazgos($sinFundamento));
        $this->assertSame([], $this->hallazgos($conFundamento));
    }

    #[Test]
    public function un_riesgo_con_valor_en_cero_o_sin_controles_es_advertencia(): void
    {
        $riesgo = $this->riesgoCompleto(['probabilidad' => 0]);
        $riesgo->controles()->detach();

        $this->assertSame(['valor_en_cero' => 'advertencia', 'sin_controles' => 'advertencia'], $this->hallazgos($riesgo));
    }

    #[Test]
    public function un_riesgo_sin_evaluacion_es_error(): void
    {
        $sinEvaluacion = $this->riesgoCompleto(['evaluacion' => null]);
        $deOtroMetodo = $this->riesgoCompleto(['evaluacion' => ['metodo' => 'ambiental', 'respuestas' => []]]);

        $this->assertSame(['sin_evaluacion' => 'error'], $this->hallazgos($sinEvaluacion));
        $this->assertSame(['sin_evaluacion' => 'error'], $this->hallazgos($deOtroMetodo));
    }

    #[Test]
    public function un_recalculo_validado_resuelve_la_falta_de_evaluacion(): void
    {
        $riesgo = $this->riesgoCompleto(['evaluacion' => null]);
        $this->proponer($riesgo, ['campos' => ['impacto' => 5, 'probabilidad' => 5, 'evaluacion' => self::EVALUACION_CLASICA]]);

        $this->assertSame(['sin_evaluacion' => 'en_curso'], $this->hallazgos($riesgo));
    }

    #[Test]
    public function un_riesgo_sin_area_es_un_error(): void
    {
        $riesgo = $this->riesgoCompleto();
        $riesgo->update(['area_id' => null]);

        $this->assertSame('error', $this->hallazgos($riesgo)['sin_area']);
    }

    #[Test]
    public function un_elemento_sin_responsable_es_advertencia(): void
    {
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'user_id' => null]);
        $plan->riesgos()->attach(Riesgo::factory()->create(['area_id' => $this->area->id])->id);
        $plan->tareas()->attach(Tarea::factory()->create(['area_id' => $this->area->id])->id);

        $this->assertSame(['sin_responsable' => 'advertencia'], $this->hallazgos($plan));
    }

    #[Test]
    public function un_control_sin_cuestionario_en_cero_o_sin_riesgos_es_advertencia(): void
    {
        $completo = Control::factory()->valorado(3)->create(['area_id' => $this->area->id]);
        $completo->riesgos()->attach(Riesgo::factory()->create(['area_id' => $this->area->id])->id);
        $this->assertSame([], $this->hallazgos($completo));

        $enCero = Control::factory()->valorado(8, operativo: false)->create(['area_id' => $this->area->id]);
        $this->assertSame([
            'mitigacion_en_cero' => 'advertencia',
            'sin_riesgos' => 'advertencia',
        ], $this->hallazgos($enCero));

        // Uno de antes del cuestionario conserva su número, pero falta contestarlo.
        $viejo = Control::factory()->create(['area_id' => $this->area->id, 'mitigacion_default' => 4]);
        $viejo->riesgos()->attach(Riesgo::factory()->create(['area_id' => $this->area->id])->id);
        $this->assertSame(['sin_cuestionario' => 'advertencia'], $this->hallazgos($viejo));
    }

    #[Test]
    public function un_objetivo_sin_riesgos_es_advertencia(): void
    {
        $objetivo = $this->objetivo();

        $this->assertSame(['sin_riesgos' => 'advertencia'], $this->hallazgos($objetivo));

        $objetivo->riesgos()->attach(Riesgo::factory()->create(['area_id' => $this->area->id])->id);
        $this->assertSame([], $this->hallazgos($objetivo));
    }

    #[Test]
    public function un_plan_sin_riesgos_ni_tareas_son_advertencias(): void
    {
        $plan = PlanAccion::factory()->sinValorar()->create(['area_id' => $this->area->id]);

        $this->assertSame(['sin_riesgos' => 'advertencia', 'sin_tareas' => 'advertencia', 'sin_valor' => 'advertencia'], $this->hallazgos($plan));
    }

    #[Test]
    public function una_tarea_sin_plan_es_error_y_sin_fecha_o_vencida_advertencia(): void
    {
        $sinPlan = Tarea::factory()->create(['area_id' => $this->area->id, 'fecha' => now()->addMonth()]);
        $this->assertSame(['sin_plan' => 'error'], $this->hallazgos($sinPlan));

        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id]);

        $sinFecha = Tarea::factory()->create(['area_id' => $this->area->id, 'fecha' => null]);
        $vencida = Tarea::factory()->create(['area_id' => $this->area->id, 'fecha' => now()->subDay(), 'porcentaje_avance' => 40]);
        $vencidaCompleta = Tarea::factory()->create(['area_id' => $this->area->id, 'fecha' => now()->subDay(), 'porcentaje_avance' => 100]);
        $plan->tareas()->attach([$sinFecha->id, $vencida->id, $vencidaCompleta->id]);

        $this->assertSame(['sin_fecha' => 'advertencia'], $this->hallazgos($sinFecha));
        $this->assertSame(['vencida' => 'advertencia'], $this->hallazgos($vencida));
        $this->assertSame([], $this->hallazgos($vencidaCompleta));
    }
}
