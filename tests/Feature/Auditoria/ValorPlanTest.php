<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\AccionActualizacionModal;
use App\Livewire\Auditoria\PlanAccion\Show\ValorPlan;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use App\Services\Auditoria\ValidacionMasivaService;
use Database\Factories\Auditoria\PlanAccionFactory;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valoración del plan por cuestionario (D-050): la estimación (card "Valor del
 * plan", obligatoria para validar) y la evaluación final (plan aprobado al
 * 100%), con sus permisos y su paso por propuesta. El cálculo del residual con
 * esas valoraciones está en MitigacionPlanTest.
 */
class ValorPlanTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $empleado;

    private User $gerente;

    private User $comite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
    }

    /** Plan del área en $estado, con una tarea aprobada al $avance. */
    private function plan(string $estado, int $avance = 100): PlanAccion
    {
        $plan = PlanAccion::factory()->sinValorar()->create([
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
            'area_id' => $this->gerencia->id,
        ]);
        $plan->tareas()->attach(Tarea::factory()->create([
            'porcentaje_avance' => $avance,
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerencia->id,
        ]));

        return $plan;
    }

    /** Riesgo 8/8 (total 16) asociado al plan. */
    private function riesgoDe(PlanAccion $plan): Riesgo
    {
        $riesgo = Riesgo::factory()->create(['area_id' => $this->gerencia->id, 'impacto' => 8, 'probabilidad' => 8]);
        $riesgo->planesAccion()->attach($plan->id);

        return $riesgo;
    }

    private function residual(Riesgo $riesgo): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])->findOrFail($riesgo->id)->valor_residual;
    }

    private function valorar(User $user, PlanAccion $plan, string $tipo, int $impacto, int $probabilidad)
    {
        return Livewire::actingAs($user)
            ->test(ValorPlan::class, ['plan' => $plan])
            ->call('abrir', $tipo)
            ->set('respuestas', PlanAccionFactory::evaluacion($impacto, $probabilidad))
            ->call('guardar');
    }

    // -------------------------------------------------------
    // Estimación
    // -------------------------------------------------------

    #[Test]
    public function en_borrador_la_estimacion_se_guarda_en_el_acto(): void
    {
        $plan = $this->plan('borrador');

        $this->valorar($this->empleado, $plan, 'estimada', 6, 4)->assertHasNoErrors()->assertSet('abierto', null);

        $plan->refresh();
        $this->assertEquals(PlanAccionFactory::evaluacion(6, 4), $plan->evaluacion_estimada);
        $this->assertSame(['impacto' => 6, 'probabilidad' => 4], $plan->reduccion_nominal);
        $this->assertSame('edicion', $plan->actualizaciones()->sole()->data['tipo']);
    }

    #[Test]
    public function la_evaluacion_del_plan_exige_las_diez_respuestas_de_si_o_no(): void
    {
        $plan = $this->plan('borrador');
        $respuestas = PlanAccionFactory::evaluacion(2, 2);
        $respuestas['impacto'][1] = 2;
        unset($respuestas['probabilidad'][5]);

        Livewire::actingAs($this->empleado)
            ->test(ValorPlan::class, ['plan' => $plan])
            ->call('abrir', 'estimada')
            ->set('respuestas', $respuestas)
            ->call('guardar')
            ->assertHasErrors(['respuestas.impacto.1', 'respuestas.probabilidad.5']);

        $this->assertNull($plan->fresh()->evaluacion_estimada);
    }

    #[Test]
    public function en_un_plan_aprobado_la_estimacion_queda_como_propuesta_hasta_que_aprueba_el_comite(): void
    {
        $plan = $this->plan('aprobado');
        $plan->update(['evaluacion_estimada' => PlanAccionFactory::evaluacion(2, 0)]);
        $riesgo = $this->riesgoDe($plan);
        $this->assertSame(14, $this->residual($riesgo));

        $this->valorar($this->gerente, $plan, 'estimada', 6, 0)->assertHasNoErrors();

        $propuesta = $plan->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertSame(Estado::validado()->id, $propuesta->estado_id);
        $this->assertSame(14, $this->residual($riesgo)); // la propuesta no mueve el residual

        $propuesta->marcarAprobada($this->comite);

        $this->assertSame(10, $this->residual($riesgo));
    }

    #[Test]
    public function el_modal_de_aprobacion_muestra_la_valoracion_propuesta_en_texto(): void
    {
        // Regresión: el modal pintaba el array crudo de la evaluación (htmlspecialchars TypeError).
        $plan = $this->plan('aprobado');
        $plan->update(['evaluacion_estimada' => PlanAccionFactory::evaluacion(2, 0)]);
        $this->valorar($this->gerente, $plan, 'estimada', 6, 4);
        $propuesta = $plan->actualizaciones()->propuestasPendientes('campos')->sole();

        Livewire::actingAs($this->comite)
            ->test(AccionActualizacionModal::class)
            ->call('abrir', $propuesta->id, 'aprobar')
            ->assertSee('Valor estimado')
            ->assertSee('Prob. −4 · Imp. −6');
    }

    #[Test]
    public function una_propuesta_pendiente_frena_otra_sobre_la_misma_valoracion(): void
    {
        $plan = $this->plan('aprobado');
        $plan->update(['evaluacion_estimada' => PlanAccionFactory::evaluacion(2, 0)]);
        $this->valorar($this->empleado, $plan, 'estimada', 6, 0);

        Livewire::actingAs($this->gerente)
            ->test(ValorPlan::class, ['plan' => $plan])
            ->call('abrir', 'estimada')
            ->assertSet('abierto', null);

        $this->assertCount(1, $plan->actualizaciones()->propuestasPendientes('campos')->get());
    }

    #[Test]
    public function quien_no_gestiona_el_plan_recibe_403(): void
    {
        $otra = Area::create(['nombre' => 'Gerencia Ventas', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $otra->id]);
        $plan = $this->plan('borrador');

        Livewire::actingAs($ajeno)
            ->test(ValorPlan::class, ['plan' => $plan])
            ->call('abrir', 'estimada')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(ValorPlan::class, ['plan' => $plan])
            ->set('abierto', 'estimada')
            ->set('respuestas', PlanAccionFactory::evaluacion(6, 4))
            ->call('guardar')
            ->assertForbidden();

        $this->assertNull($plan->fresh()->evaluacion_estimada);
    }

    #[Test]
    public function el_comite_no_valora_planes(): void
    {
        $plan = $this->plan('aprobado');

        Livewire::actingAs($this->comite)
            ->test(ValorPlan::class, ['plan' => $plan])
            ->set('abierto', 'estimada')
            ->set('respuestas', PlanAccionFactory::evaluacion(6, 4))
            ->call('guardar')
            ->assertHasErrors('respuestas');

        $this->assertNull($plan->fresh()->evaluacion_estimada);
        $this->assertCount(0, $plan->actualizaciones);
    }

    // -------------------------------------------------------
    // Requisito para validar
    // -------------------------------------------------------

    #[Test]
    public function un_plan_sin_estimacion_no_se_valida(): void
    {
        $plan = $this->plan('borrador');

        $this->actingAs($this->gerente)
            ->post(route('auditoria.planes.validar', $plan))
            ->assertSessionHas('error');
        $this->assertSame('borrador', $plan->fresh()->estado->nombre);

        $plan->update(['evaluacion_estimada' => PlanAccionFactory::evaluacion(0, 0)]);

        $this->actingAs($this->gerente)->post(route('auditoria.planes.validar', $plan));
        $this->assertSame('validado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function un_plan_validado_sin_estimacion_no_se_aprueba(): void
    {
        $plan = $this->plan('validado');

        $this->actingAs($this->comite)
            ->post(route('auditoria.planes.aprobar', $plan))
            ->assertSessionHas('error');

        $this->assertSame('validado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function la_validacion_masiva_no_valida_un_plan_sin_estimacion(): void
    {
        $plan = $this->plan('borrador');

        $resultado = app(ValidacionMasivaService::class)->ejecutar($plan, 'validar', [], [], $this->gerente);

        $this->assertArrayHasKey('error', $resultado);
        $this->assertSame('borrador', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function la_cascada_de_un_riesgo_no_valida_un_plan_sin_estimacion(): void
    {
        $plan = $this->plan('borrador');
        $riesgo = $this->riesgoDe($plan);

        $resultado = app(ValidacionMasivaService::class)->ejecutar(
            $riesgo, 'validar', [['tipo' => 'plan', 'id' => $plan->id, 'nivel' => 0]], [], $this->gerente
        );

        $this->assertArrayHasKey('error', $resultado);
        $this->assertSame('borrador', $plan->fresh()->estado->nombre);
    }

    // -------------------------------------------------------
    // Evaluación final
    // -------------------------------------------------------

    #[Test]
    public function la_evaluacion_final_no_se_habilita_antes_del_100(): void
    {
        $plan = $this->plan('aprobado', avance: 80);
        $plan->update(['evaluacion_estimada' => PlanAccionFactory::evaluacion(6, 0)]);

        Livewire::actingAs($this->gerente)
            ->test(ValorPlan::class, ['plan' => $plan])
            ->call('abrir', 'final')
            ->assertSet('abierto', null)
            ->set('abierto', 'final')
            ->set('respuestas', PlanAccionFactory::evaluacion(2, 2))
            ->call('guardar')
            ->assertHasErrors('respuestas');

        $this->assertNull($plan->fresh()->evaluacion_final);
    }

    #[Test]
    public function la_evaluacion_final_pide_el_plan_aprobado_y_estimado(): void
    {
        $validado = $this->plan('validado');
        $validado->update(['evaluacion_estimada' => PlanAccionFactory::evaluacion(6, 0)]);
        $sinEstimar = $this->plan('aprobado');

        $this->assertNotNull($validado->load('estado', 'tareas.estado')->motivoBloqueoValoracion($this->gerente, 'evaluacion_final'));
        $this->assertNotNull($sinEstimar->load('estado', 'tareas.estado')->motivoBloqueoValoracion($this->gerente, 'evaluacion_final'));
    }

    #[Test]
    public function la_evaluacion_final_arranca_con_las_respuestas_de_la_estimada(): void
    {
        $plan = $this->plan('aprobado');
        $plan->update(['evaluacion_estimada' => PlanAccionFactory::evaluacion(6, 4)]);

        Livewire::actingAs($this->gerente)
            ->test(ValorPlan::class, ['plan' => $plan])
            ->call('abrir', 'final')
            ->assertSet('abierto', 'final')
            ->assertSet('respuestas', PlanAccionFactory::evaluacion(6, 4));
    }

    #[Test]
    public function con_la_evaluacion_final_aprobada_el_plan_queda_finalizado(): void
    {
        $plan = $this->plan('aprobado');
        $plan->update(['evaluacion_estimada' => PlanAccionFactory::evaluacion(6, 0)]);
        $riesgo = $this->riesgoDe($plan);
        $this->assertSame(10, $this->residual($riesgo));

        $this->valorar($this->empleado, $plan, 'final', 2, 2)->assertHasNoErrors();

        $propuesta = $plan->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertSame(Estado::borrador()->id, $propuesta->estado_id);

        $propuesta->marcarValidada($this->gerente);
        $this->assertSame(10, $this->residual($riesgo)); // validada, espera al comité
        $this->assertFalse(PlanAccion::with('tareas.estado')->find($plan->id)->finalizado);

        Actualizacion::find($propuesta->id)->marcarAprobada($this->comite);

        $this->assertSame(12, $this->residual($riesgo)); // el real (2/2) reemplaza al estimado (6/0)
        $this->assertTrue(PlanAccion::with('tareas.estado')->find($plan->id)->finalizado);
    }
}
