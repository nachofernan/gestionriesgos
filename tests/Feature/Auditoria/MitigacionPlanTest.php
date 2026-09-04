<?php

namespace Tests\Feature\Auditoria;

use App\Livewire\Auditoria\Modal\DetallePlan;
use App\Livewire\Auditoria\PlanAccion\Index\Search as PlanAccionSearch;
use App\Livewire\Auditoria\PlanAccion\Show\GestionTareas;
use App\Livewire\Auditoria\Riesgo\Show\GestionControles;
use App\Livewire\Auditoria\Riesgo\Show\GestionPlanes;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre las tres reglas de mitigación del valor_residual:
 * 1) Sólo los controles en estado "aprobado" descuentan mitigación.
 * 2) El avance del plan promedia sólo las tareas aprobadas; las tareas en estado
 *    "borrado" quedan fuera del plan (no cuentan ni se muestran).
 * 3) La mitigación de un plan sólo cuenta cuando el plan está al 100% de avance,
 *    combinada de forma aditiva con la de los controles.
 */
class MitigacionPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function tareaConEstado(int $porcentaje, string $estado): Tarea
    {
        return Tarea::factory()->create([
            'porcentaje_avance' => $porcentaje,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
        ]);
    }

    /**
     * Plan en estado "aprobado" con una única tarea aprobada al porcentaje dado
     * (el caso que descuenta: sólo un plan aprobado y al 100% mitiga).
     */
    private function planCon(int $porcentaje): PlanAccion
    {
        $plan = PlanAccion::factory()->create(['estado_id' => Estado::aprobado()->id]);
        $plan->tareas()->attach($this->tareaConEstado($porcentaje, 'aprobado'));

        return $plan;
    }

    private function controlAprobado(): Control
    {
        return Control::factory()->create(['estado_id' => Estado::aprobado()->id]);
    }

    // -------------------------------------------------------
    // Regla 3 — mitigación de planes al 100%
    // -------------------------------------------------------

    #[Test]
    public function un_plan_al_100_descuenta_su_mitigacion_del_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $plan = $this->planCon(100);

        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertEquals(16, $riesgo->fresh()->valor_total);
        $this->assertEquals(10, $riesgo->fresh()->valor_residual); // 16 - 6
    }

    #[Test]
    public function un_plan_por_debajo_del_100_no_afecta_el_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $plan = $this->planCon(99);

        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertEquals(16, $riesgo->fresh()->valor_residual); // sin descuento
    }

    #[Test]
    public function un_plan_validado_al_100_no_afecta_el_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $plan = $this->planCon(100);
        $plan->update(['estado_id' => Estado::validado()->id]);

        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertTrue($plan->fresh()->estaCompleto());
        $this->assertEquals(16, $riesgo->fresh()->valor_residual); // validado no mitiga, aunque esté completo
    }

    #[Test]
    public function al_aprobar_el_plan_completo_recien_ahi_baja_el_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $plan = $this->planCon(100);
        $plan->update(['estado_id' => Estado::validado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertEquals(16, $riesgo->fresh()->valor_residual);

        $plan->update(['estado_id' => Estado::aprobado()->id]);

        $this->assertEquals(10, $riesgo->fresh()->valor_residual); // 16 - 6
    }

    #[Test]
    public function el_preview_en_vivo_de_planes_solo_cuenta_los_aprobados_al_100()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $aprobado = $this->planCon(100);
        $aprobado->update(['estado_id' => Estado::aprobado()->id]);
        $validado = $this->planCon(100);
        $validado->update(['estado_id' => Estado::validado()->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $aprobado->id)
            ->call('actualizarMitigacion', $aprobado->id, 6)
            ->call('agregar', $validado->id)
            ->call('actualizarMitigacion', $validado->id, 5)
            ->assertDispatched('residual-actualizado', valor: 10); // 16 - 6 (el validado no descuenta)
    }

    #[Test]
    public function un_plan_sin_tareas_no_afecta_el_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]);
        $plan = PlanAccion::factory()->create();

        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertNull($plan->fresh()->avance);
        $this->assertEquals(16, $riesgo->fresh()->valor_residual);
    }

    #[Test]
    public function la_mitigacion_del_plan_y_la_de_los_controles_aprobados_se_suman()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $control = $this->controlAprobado();
        $plan = $this->planCon(100);

        $riesgo->controles()->attach($control->id, ['mitigacion' => 5]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);

        $this->assertEquals(5, $riesgo->fresh()->valor_residual); // 16 - 5 - 6
    }

    #[Test]
    public function el_valor_residual_nunca_baja_de_cero()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 2, 'probabilidad' => 2]); // total 4
        $plan = $this->planCon(100);

        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 20]);

        $this->assertEquals(0, $riesgo->fresh()->valor_residual);
    }

    // -------------------------------------------------------
    // Regla 1 — sólo mitigan los controles aprobados
    // -------------------------------------------------------

    #[Test]
    public function un_control_no_aprobado_no_baja_el_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $control = Control::factory()->create(['estado_id' => Estado::borrador()->id]);

        $riesgo->controles()->attach($control->id, ['mitigacion' => 5]);

        $this->assertEquals(16, $riesgo->fresh()->valor_residual); // el borrador no mitiga
    }

    #[Test]
    public function al_aprobar_el_control_recien_ahi_baja_el_valor_residual()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $control = Control::factory()->create(['estado_id' => Estado::borrador()->id]);
        $riesgo->controles()->attach($control->id, ['mitigacion' => 5]);

        $this->assertEquals(16, $riesgo->fresh()->valor_residual);

        $control->update(['estado_id' => Estado::aprobado()->id]);

        $this->assertEquals(11, $riesgo->fresh()->valor_residual); // 16 - 5
    }

    #[Test]
    public function el_preview_en_vivo_de_controles_solo_cuenta_los_aprobados()
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]); // total 16
        $aprobado = Control::factory()->create(['estado_id' => Estado::aprobado()->id, 'mitigacion_default' => 5]);
        $borrador = Control::factory()->create(['estado_id' => Estado::borrador()->id, 'mitigacion_default' => 3]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $aprobado->id)
            ->call('agregar', $borrador->id)
            ->assertDispatched('residual-actualizado', valor: 11); // 16 - 5 (el borrador no descuenta)
    }

    #[Test]
    public function la_vista_de_riesgo_solo_lista_las_tareas_aprobadas_del_plan()
    {
        $riesgo = Riesgo::factory()->create(['estado_id' => Estado::aprobado()->id, 'impacto' => 5, 'probabilidad' => 5]);
        $plan = PlanAccion::factory()->create();
        $aprobada = $this->tareaConEstado(100, 'aprobado');
        $aprobada->update(['nombre' => 'Tarea Aprobada Visible']);
        $validada = $this->tareaConEstado(50, 'validado');
        $validada->update(['nombre' => 'Tarea Validada Oculta']);
        $plan->tareas()->attach([$aprobada->id, $validada->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 0]);

        $this->actingAs(User::factory()->create())
            ->get(route('auditoria.riesgos.show', $riesgo))
            ->assertOk()
            ->assertSee('Tarea Aprobada Visible')
            ->assertDontSee('Tarea Validada Oculta');
    }

    #[Test]
    public function el_modal_de_plan_muestra_el_avance_y_el_conteo_solo_de_tareas_aprobadas()
    {
        $plan = PlanAccion::factory()->create();
        $plan->tareas()->attach($this->tareaConEstado(100, 'aprobado'));
        $plan->tareas()->attach($this->tareaConEstado(63, 'validado'));
        $plan->tareas()->attach($this->tareaConEstado(61, 'validado'));

        Livewire::actingAs(User::factory()->create())
            ->test(DetallePlan::class)
            ->call('abrir', $plan->id)
            ->assertSee('100%') // avance real (sólo la aprobada), no el promedio de las 3 (75%)
            ->assertDontSee('75%')
            ->assertSee('Tarea aprobada'); // singular: 1 sola tarea aprobada, no "Tareas aprobadas"
    }

    #[Test]
    public function el_listado_de_planes_muestra_el_avance_solo_de_tareas_aprobadas()
    {
        $plan = PlanAccion::factory()->create();
        $plan->tareas()->attach($this->tareaConEstado(100, 'aprobado'));
        $plan->tareas()->attach($this->tareaConEstado(63, 'validado'));
        $plan->tareas()->attach($this->tareaConEstado(61, 'validado'));

        Livewire::actingAs(User::factory()->create())
            ->test(PlanAccionSearch::class)
            ->assertViewHas('planes', function ($planes) use ($plan) {
                $p = $planes->firstWhere('id', $plan->id);

                return $p && $p->avance === 100; // no el promedio de las 3 (75)
            });
    }

    // -------------------------------------------------------
    // Regla 2 — avance por tareas aprobadas; "borrado" fuera del plan
    // -------------------------------------------------------

    #[Test]
    public function una_tarea_aprobada_al_100_completa_el_plan()
    {
        $plan = $this->planCon(100);

        $this->assertEquals(100, $plan->fresh()->avance);
        $this->assertTrue($plan->fresh()->estaCompleto());
    }

    #[Test]
    public function el_avance_del_plan_promedia_solo_las_tareas_aprobadas()
    {
        $plan = PlanAccion::factory()->create();
        $plan->tareas()->attach($this->tareaConEstado(100, 'aprobado'));
        $plan->tareas()->attach($this->tareaConEstado(0, 'borrador'));
        $plan->tareas()->attach($this->tareaConEstado(50, 'validado'));

        // Sólo la aprobada cuenta: promedio = 100, no (100+0+50)/3.
        $this->assertEquals(100, $plan->fresh()->avance);
    }

    #[Test]
    public function un_plan_sin_tareas_aprobadas_no_tiene_avance()
    {
        $plan = PlanAccion::factory()->create();
        $plan->tareas()->attach($this->tareaConEstado(100, 'borrador'));
        $plan->tareas()->attach($this->tareaConEstado(80, 'validado'));

        $this->assertNull($plan->fresh()->avance);
        $this->assertFalse($plan->fresh()->estaCompleto());
    }

    #[Test]
    public function una_tarea_en_estado_borrado_no_cuenta_para_el_avance()
    {
        $plan = PlanAccion::factory()->create();
        $plan->tareas()->attach($this->tareaConEstado(100, 'aprobado'));
        $plan->tareas()->attach($this->tareaConEstado(0, 'borrado'));

        $this->assertEquals(100, $plan->fresh()->avance);
    }

    #[Test]
    public function una_tarea_en_estado_borrado_no_se_muestra_en_la_gestion_de_tareas_del_plan()
    {
        $plan = PlanAccion::factory()->create();
        $vigente = $this->tareaConEstado(100, 'aprobado');
        $borrada = $this->tareaConEstado(0, 'borrado');
        $plan->tareas()->attach([$vigente->id, $borrada->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionTareas::class, ['plan' => $plan])
            ->assertSet('seleccionados', fn ($sel) => collect($sel)->pluck('id')->contains($vigente->id)
                && ! collect($sel)->pluck('id')->contains($borrada->id))
            ->assertSet('ocultosIds', [$borrada->id]);
    }

    #[Test]
    public function guardar_tareas_de_un_plan_borrador_preserva_las_tareas_borrado_ocultas()
    {
        $plan = PlanAccion::factory()->create(); // borrador
        $vigente = $this->tareaConEstado(100, 'aprobado');
        $borrada = $this->tareaConEstado(0, 'borrado');
        $plan->tareas()->attach([$vigente->id, $borrada->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionTareas::class, ['plan' => $plan])
            ->call('activarEdicion')
            ->call('guardar');

        // La tarea borrada oculta sigue asociada al pivot, no se detachó al guardar.
        $idsPivot = $plan->fresh()->tareas->pluck('id');
        $this->assertTrue($idsPivot->contains($borrada->id));
        $this->assertTrue($idsPivot->contains($vigente->id));
    }
}
