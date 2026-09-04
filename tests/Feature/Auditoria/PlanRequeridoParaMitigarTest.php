<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Livewire\Auditoria\Riesgo\Show\GestionPlanes;
use App\Livewire\Auditoria\ValidacionCascadaModal;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use App\Services\Auditoria\ValidacionMasivaService;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre la regla nueva: un riesgo con respuesta "Reducir/Mitigar" no sólo debe
 * tener un plan de acción asociado, sino que ese plan debe estar validado para
 * validar el riesgo, y aprobado para aprobarlo (Riesgo::motivosBloqueoValidacion()
 * y motivosBloqueoAprobacion()). También cubre que la cascada ofrece el plan
 * como bloqueante del riesgo (no al revés, que era el bug reportado: antes
 * ValidacionMasivaService::analizarPlanAccion() forzaba aprobar el riesgo para
 * poder validar/aprobar el plan).
 */
class PlanRequeridoParaMitigarTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $comite;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->area = Area::create(['nombre' => 'Gerencia', 'area_padre_id' => null]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->area->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
    }

    private function riesgoMitigarConObjetivo(): Riesgo
    {
        $riesgo = Riesgo::factory()->borrador()->create([
            'area_id' => $this->area->id,
            'respuesta' => RespuestaRiesgo::Mitigar,
        ]);

        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo del riesgo',
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->area->id,
            'user_id' => $this->gerente->id,
        ]);
        $riesgo->objetivos()->attach($objetivo->id);

        return $riesgo;
    }

    // -------------------------------------------------------
    // Reglas del modelo
    // -------------------------------------------------------

    #[Test]
    public function un_riesgo_mitigar_con_plan_en_borrador_no_puede_validarse_aunque_el_plan_exista()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id]); // borrador por defecto
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $motivos = $riesgo->motivosBloqueoValidacion();

        $this->assertNotEmpty($motivos);
        $this->assertStringContainsString('debe estar validado', $motivos[0]);
    }

    #[Test]
    public function un_riesgo_mitigar_con_plan_validado_puede_validarse()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $this->assertEmpty($riesgo->motivosBloqueoValidacion());
    }

    #[Test]
    public function un_riesgo_mitigar_sin_ningun_plan_no_puede_validarse()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();

        $motivos = $riesgo->motivosBloqueoValidacion();

        $this->assertNotEmpty($motivos);
        $this->assertStringContainsString('al menos un plan de acción', $motivos[0]);
    }

    #[Test]
    public function un_riesgo_mitigar_con_plan_validado_pero_no_aprobado_no_puede_aprobarse()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $riesgo->update(['estado_id' => Estado::validado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $motivos = $riesgo->motivosBloqueoAprobacion();

        $this->assertNotEmpty($motivos);
        $this->assertStringContainsString('plan de acción aprobado', $motivos[0]);
    }

    #[Test]
    public function un_riesgo_mitigar_con_plan_aprobado_puede_aprobarse()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $riesgo->update(['estado_id' => Estado::validado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $this->assertEmpty($riesgo->motivosBloqueoAprobacion());
    }

    #[Test]
    public function un_riesgo_con_respuesta_distinta_de_mitigar_no_exige_plan_para_validar_ni_aprobar()
    {
        $riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $this->area->id,
            'respuesta' => RespuestaRiesgo::Aceptar,
            'fundamento' => 'Riesgo aceptado por bajo impacto.',
        ]);
        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo del riesgo',
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->area->id,
            'user_id' => $this->gerente->id,
        ]);
        $riesgo->objetivos()->attach($objetivo->id);

        $this->assertEmpty($riesgo->motivosBloqueoValidacion());
        $this->assertEmpty($riesgo->motivosBloqueoAprobacion());
    }

    // -------------------------------------------------------
    // Controlador (camino feliz + caso feo)
    // -------------------------------------------------------

    #[Test]
    public function el_controlador_bloquea_validar_un_riesgo_mitigar_con_plan_en_borrador()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.validar', $riesgo))
            ->assertSessionHas('error');

        $this->assertEquals('borrador', $riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function el_controlador_bloquea_aprobar_un_riesgo_mitigar_con_plan_solo_validado()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $riesgo->update(['estado_id' => Estado::validado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $this->actingAs($this->comite)
            ->post(route('auditoria.riesgos.aprobar', $riesgo))
            ->assertSessionHas('error');

        $this->assertEquals('validado', $riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function el_controlador_permite_aprobar_un_riesgo_mitigar_con_plan_ya_aprobado()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $riesgo->update(['estado_id' => Estado::validado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $this->actingAs($this->comite)
            ->post(route('auditoria.riesgos.aprobar', $riesgo))
            ->assertSessionHas('ok');

        $this->assertEquals('aprobado', $riesgo->fresh()->estado->nombre);
    }

    // -------------------------------------------------------
    // Cascada: el plan es bloqueante del riesgo, no al revés
    // -------------------------------------------------------

    #[Test]
    public function la_cascada_de_validacion_del_riesgo_ofrece_el_plan_como_bloqueante()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $analisis = app(ValidacionMasivaService::class)->analizar($riesgo, 'validar', $this->gerente);

        $tiposBloqueantes = collect($analisis['bloqueantes'])->pluck('tipo');
        $this->assertContains('plan', $tiposBloqueantes);
    }

    #[Test]
    public function validar_el_riesgo_desde_la_cascada_seleccionando_el_plan_valida_ambos()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar')
            ->call('confirmar')
            ->assertSet('error', null);

        $this->assertEquals('validado', $riesgo->fresh()->estado->nombre);
        $this->assertEquals('validado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function la_cascada_de_validacion_del_plan_ya_no_ofrece_el_riesgo_como_bloqueante()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $analisis = app(ValidacionMasivaService::class)->analizar($plan, 'validar', $this->gerente);

        $this->assertEmpty($analisis['bloqueantes']);
    }

    #[Test]
    public function validar_el_plan_directamente_no_exige_ni_toca_el_estado_del_riesgo()
    {
        $riesgo = $this->riesgoMitigarConObjetivo(); // riesgo queda en borrador
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $this->actingAs($this->gerente)
            ->post(route('auditoria.planes.validar', $plan))
            ->assertSessionHas('ok');

        $this->assertEquals('validado', $plan->fresh()->estado->nombre);
        $this->assertEquals('borrador', $riesgo->fresh()->estado->nombre); // el riesgo no se toca
    }

    // -------------------------------------------------------
    // GestionPlanes: no se puede dejar el riesgo sin un plan que
    // respalde su estado actual quitándolo desde la edición de la relación
    // (el bug reportado: motivosBloqueoValidacion/Aprobacion sólo protegían
    // el avance de estado, no que te lo saquen estando ya validado/aprobado).
    // -------------------------------------------------------

    #[Test]
    public function un_riesgo_aprobado_mitigar_no_puede_quitar_su_unico_plan_aprobado()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);
        $riesgo->update(['estado_id' => Estado::aprobado()->id]);

        Livewire::actingAs($this->gerente)
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $plan->id)
            ->assertSet('error', fn ($error) => str_contains($error, 'aprobado'))
            ->assertSet('seleccionados', fn ($sel) => collect($sel)->pluck('id')->contains($plan->id));
    }

    #[Test]
    public function un_riesgo_validado_mitigar_no_puede_quitar_su_unico_plan_validado()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);
        $riesgo->update(['estado_id' => Estado::validado()->id]);

        Livewire::actingAs($this->gerente)
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $plan->id)
            ->assertSet('error', fn ($error) => str_contains($error, 'validado'))
            ->assertSet('seleccionados', fn ($sel) => collect($sel)->pluck('id')->contains($plan->id));
    }

    #[Test]
    public function un_riesgo_validado_mitigar_puede_quitar_un_plan_en_borrador_si_otro_lo_respalda()
    {
        $riesgo = $this->riesgoMitigarConObjetivo();
        $respaldo = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $borrador = PlanAccion::factory()->create(['area_id' => $this->area->id]); // borrador
        $riesgo->planesAccion()->attach([$respaldo->id => ['mitigacion' => 5], $borrador->id => ['mitigacion' => 0]]);
        $riesgo->update(['estado_id' => Estado::validado()->id]);

        Livewire::actingAs($this->gerente)
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $borrador->id)
            ->assertSet('error', '')
            ->assertSet('seleccionados', fn ($sel) => ! collect($sel)->pluck('id')->contains($borrador->id));
    }

    #[Test]
    public function un_riesgo_mitigar_en_borrador_puede_quedarse_sin_planes_desde_gestion_planes()
    {
        $riesgo = $this->riesgoMitigarConObjetivo(); // borrador
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        Livewire::actingAs($this->gerente)
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $plan->id)
            ->assertSet('error', '')
            ->assertSet('seleccionados', []);
    }

    #[Test]
    public function un_riesgo_aprobado_con_respuesta_distinta_de_mitigar_puede_quedarse_sin_planes()
    {
        $riesgo = Riesgo::factory()->aprobado()->create([
            'area_id' => $this->area->id,
            'respuesta' => RespuestaRiesgo::Aceptar,
            'fundamento' => 'Riesgo aceptado por bajo impacto.',
        ]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        Livewire::actingAs($this->gerente)
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $plan->id)
            ->assertSet('error', '')
            ->assertSet('seleccionados', []);
    }
}
