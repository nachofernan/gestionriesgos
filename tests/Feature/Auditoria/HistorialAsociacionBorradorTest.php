<?php

namespace Tests\Feature\Auditoria;

use App\Livewire\Auditoria\Riesgo\Show\GestionControles;
use App\Livewire\Auditoria\Riesgo\Show\GestionObjetivos;
use App\Livewire\Auditoria\Riesgo\Show\GestionPlanes;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Asociar/quitar controles, planes u objetivos a un riesgo EN BORRADOR debe dejar
 * rastro en el historial de actualizaciones (igual que la rama de propuesta de
 * cambio), pero sólo si la selección realmente cambió. Además, los modales de
 * búsqueda para asociar no deben ofrecer entidades en estado 'borrado'.
 */
class HistorialAsociacionBorradorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function riesgoBorrador(): Riesgo
    {
        return Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8]);
    }

    // -------------------------------------------------------
    // Registro en el historial (borrador)
    // -------------------------------------------------------

    /** @test */
    public function asociar_un_control_a_un_riesgo_en_borrador_registra_una_actualizacion_con_diff()
    {
        $riesgo = $this->riesgoBorrador();
        $control = Control::factory()->create(['nombre' => 'Control Alfa', 'mitigacion_default' => 4]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $control->id)
            ->call('guardar');

        $this->assertTrue($riesgo->fresh()->controles->pluck('id')->contains($control->id));

        $act = $riesgo->actualizaciones()->latest('id')->first();
        $this->assertNotNull($act);
        $this->assertEquals('borrador', $act->estado->nombre);
        $this->assertEquals('edicion', $act->data['tipo']);
        $agrega = $act->data['diff']['relaciones']['controles']['agrega'];
        $this->assertCount(1, $agrega);
        $this->assertEquals($control->id, $agrega[0]['id']);
        $this->assertEquals(4, $agrega[0]['mitigacion']);
    }

    /** @test */
    public function asociar_un_plan_a_un_riesgo_en_borrador_registra_una_actualizacion_con_diff()
    {
        $riesgo = $this->riesgoBorrador();
        $plan = PlanAccion::factory()->create(['nombre' => 'Plan Beta']);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $plan->id)
            ->call('guardar');

        $this->assertTrue($riesgo->fresh()->planesAccion->pluck('id')->contains($plan->id));

        $act = $riesgo->actualizaciones()->latest('id')->first();
        $this->assertNotNull($act);
        $this->assertEquals('borrador', $act->estado->nombre);
        $this->assertEquals('edicion', $act->data['tipo']);
        $agrega = $act->data['diff']['relaciones']['planesAccion']['agrega'];
        $this->assertCount(1, $agrega);
        $this->assertEquals($plan->id, $agrega[0]['id']);
    }

    /** @test */
    public function asociar_un_objetivo_a_un_riesgo_en_borrador_registra_una_actualizacion_con_diff()
    {
        $riesgo = $this->riesgoBorrador();
        $objetivo = Objetivo::create(['nombre' => 'Objetivo Gamma']);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $objetivo->id)
            ->call('guardar');

        $this->assertTrue($riesgo->fresh()->objetivos->pluck('id')->contains($objetivo->id));

        $act = $riesgo->actualizaciones()->latest('id')->first();
        $this->assertNotNull($act);
        $this->assertEquals('borrador', $act->estado->nombre);
        $this->assertEquals('edicion', $act->data['tipo']);
        $agrega = $act->data['diff']['relaciones']['objetivos']['agrega'];
        $this->assertCount(1, $agrega);
        $this->assertEquals($objetivo->id, $agrega[0]['id']);
    }

    /** @test */
    public function quitar_un_objetivo_en_borrador_registra_la_baja_en_el_historial()
    {
        $riesgo = $this->riesgoBorrador();
        $a = Objetivo::create(['nombre' => 'Objetivo A']);
        $b = Objetivo::create(['nombre' => 'Objetivo B']);
        $riesgo->objetivos()->attach([$a->id, $b->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $b->id)
            ->call('guardar');

        $this->assertFalse($riesgo->fresh()->objetivos->pluck('id')->contains($b->id));

        $act = $riesgo->actualizaciones()->latest('id')->first();
        $quita = $act->data['diff']['relaciones']['objetivos']['quita'];
        $this->assertCount(1, $quita);
        $this->assertEquals($b->id, $quita[0]['id']);
    }

    // -------------------------------------------------------
    // Sin cambios → sin actualización
    // -------------------------------------------------------

    /** @test */
    public function guardar_controles_sin_cambios_no_registra_ninguna_actualizacion()
    {
        $riesgo = $this->riesgoBorrador();
        $control = Control::factory()->create(['mitigacion_default' => 4]);
        $riesgo->controles()->attach($control->id, ['mitigacion' => 4]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('guardar');

        $this->assertEquals(0, $riesgo->actualizaciones()->count());
    }

    /** @test */
    public function guardar_objetivos_sin_cambios_no_registra_ninguna_actualizacion()
    {
        $riesgo = $this->riesgoBorrador();
        $objetivo = Objetivo::create(['nombre' => 'Objetivo estable']);
        $riesgo->objetivos()->attach($objetivo->id);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('guardar');

        $this->assertEquals(0, $riesgo->actualizaciones()->count());
    }

    // -------------------------------------------------------
    // Modales de búsqueda: no ofrecen entidades 'borrado'
    // -------------------------------------------------------

    /** @test */
    public function el_modal_de_controles_no_ofrece_controles_en_estado_borrado()
    {
        $riesgo = $this->riesgoBorrador();
        $vigente = Control::factory()->create(['estado_id' => Estado::borrador()->id]);
        $borrado = Control::factory()->create(['estado_id' => Estado::borrado()->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('abrirModal')
            ->assertViewHas('resultados', fn ($r) => $r->pluck('id')->contains($vigente->id)
                && ! $r->pluck('id')->contains($borrado->id));
    }

    /** @test */
    public function el_modal_de_planes_no_ofrece_planes_en_estado_borrado()
    {
        $riesgo = $this->riesgoBorrador();
        $vigente = PlanAccion::factory()->create(['estado_id' => Estado::borrador()->id]);
        $borrado = PlanAccion::factory()->create(['estado_id' => Estado::borrado()->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('abrirModal')
            ->assertViewHas('resultados', fn ($r) => $r->pluck('id')->contains($vigente->id)
                && ! $r->pluck('id')->contains($borrado->id));
    }

    /** @test */
    public function el_modal_de_objetivos_no_ofrece_objetivos_en_estado_borrado()
    {
        $riesgo = $this->riesgoBorrador();
        $vigente = Objetivo::create(['nombre' => 'Objetivo vigente', 'estado_id' => Estado::borrador()->id]);
        $borrado = Objetivo::create(['nombre' => 'Objetivo borrado', 'estado_id' => Estado::borrado()->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('abrirModal')
            ->assertViewHas('resultados', fn ($r) => $r->pluck('id')->contains($vigente->id)
                && ! $r->pluck('id')->contains($borrado->id));
    }
}
