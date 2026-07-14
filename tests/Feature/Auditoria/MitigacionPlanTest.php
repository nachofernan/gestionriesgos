<?php

namespace Tests\Feature\Auditoria;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre la mitigación por plan de acción en el pivot plan_accion_riesgo: sólo
 * descuenta del valor_residual cuando el plan está al 100% de avance, y se
 * combina de forma aditiva con la mitigación de los controles.
 */
class MitigacionPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function planCon(int $porcentaje): PlanAccion
    {
        $plan = PlanAccion::factory()->create();
        $plan->tareas()->attach(Tarea::factory()->create(['porcentaje_avance' => $porcentaje]));

        return $plan;
    }

    /** @test */
    public function un_plan_al_100_descuenta_su_mitigacion_del_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $plan = $this->planCon(100);

        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertEquals(16, $riesgo->fresh()->valor_total);
        $this->assertEquals(10, $riesgo->fresh()->valor_residual); // 16 - 6
    }

    /** @test */
    public function un_plan_por_debajo_del_100_no_afecta_el_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $plan = $this->planCon(99);

        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertEquals(16, $riesgo->fresh()->valor_residual); // sin descuento
    }

    /** @test */
    public function un_plan_sin_tareas_no_afecta_el_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]);
        $plan = PlanAccion::factory()->create();

        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertNull($plan->fresh()->avance);
        $this->assertEquals(16, $riesgo->fresh()->valor_residual);
    }

    /** @test */
    public function la_mitigacion_del_plan_y_la_de_los_controles_se_suman()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $control = Control::factory()->create();
        $plan = $this->planCon(100);

        $riesgo->controles()->attach($control->id, ['mitigacion' => 5]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertEquals(5, $riesgo->fresh()->valor_residual); // 16 - 5 - 6
    }

    /** @test */
    public function el_valor_residual_nunca_baja_de_cero()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 2, 'probabilidad' => 2]); // total 4
        $plan = $this->planCon(100);

        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 20]);

        $this->assertEquals(0, $riesgo->fresh()->valor_residual);
    }
}
