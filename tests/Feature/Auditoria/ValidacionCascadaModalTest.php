<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Livewire\Auditoria\ValidacionCascadaModal;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre el flag sinRedireccion agregado para la pantalla de Pendientes: cuando
 * viene en true, confirmar() no redirige (a diferencia del uso normal desde
 * los show() de cada entidad) y en cambio avisa por evento de navegador para
 * que sólo esa fila se actualice. También que lo que la cascada no resuelve
 * (un riesgo sin objetivo, un plan sin estimación) se vea al abrir y no deje
 * confirmar.
 */
class ValidacionCascadaModalTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $area = $this->area = Area::create(['nombre' => 'Gerencia', 'area_padre_id' => null]);
        $this->gerente = User::factory()->gerente()->create(['area_id' => $area->id]);

        $this->riesgo = Riesgo::factory()->borrador()->create([
            'area_id' => $area->id,
            'nombre' => 'Riesgo test',
            'respuesta' => RespuestaRiesgo::Aceptar,
        ]);

        // motivosBloqueoValidacion() exige al menos un objetivo asociado.
        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo del riesgo',
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $area->id,
            'user_id' => $this->gerente->id,
        ]);
        $this->riesgo->objetivos()->attach($objetivo->id);
    }

    private Riesgo $riesgo;

    #[Test]
    public function con_sinredireccion_no_redirige_y_avisa_por_evento(): void
    {
        Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $this->riesgo->id, 'validar', true)
            ->call('confirmar')
            ->assertDispatched('cascada-procesada', tipo: 'riesgo', id: $this->riesgo->id)
            ->assertNoRedirect();

        $this->assertEquals('validado', $this->riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function sin_sinredireccion_mantiene_el_comportamiento_de_redirigir(): void
    {
        Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $this->riesgo->id, 'validar')
            ->call('confirmar')
            ->assertRedirect();

        $this->assertEquals('validado', $this->riesgo->fresh()->estado->nombre);
    }

    private function riesgoSinObjetivo(RespuestaRiesgo $respuesta = RespuestaRiesgo::Aceptar): Riesgo
    {
        return Riesgo::factory()->borrador()->create([
            'area_id' => $this->area->id,
            'nombre' => 'Riesgo sin objetivo',
            'respuesta' => $respuesta,
        ]);
    }

    #[Test]
    public function un_riesgo_sin_objetivo_muestra_el_impedimento_al_abrir_y_no_deja_confirmar(): void
    {
        $riesgo = $this->riesgoSinObjetivo();

        $modal = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar')
            ->assertSee('El riesgo no tiene objetivos asociados');

        $this->assertNotEmpty($modal->get('impedimentos'));
        $this->assertFalse($modal->instance()->puedeConfirmar());
    }

    #[Test]
    public function un_riesgo_mitigar_sin_plan_muestra_el_impedimento_al_abrir(): void
    {
        $this->riesgo->update(['respuesta' => RespuestaRiesgo::Mitigar]);

        $modal = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $this->riesgo->id, 'validar')
            ->assertSee('necesita un plan de acción asociado');

        $this->assertCount(1, $modal->get('impedimentos'));
        $this->assertFalse($modal->instance()->puedeConfirmar());
    }

    #[Test]
    public function un_plan_sin_estimacion_como_bloqueante_no_se_puede_seleccionar(): void
    {
        $this->riesgo->update(['respuesta' => RespuestaRiesgo::Mitigar]);
        $plan = PlanAccion::factory()->sinValorar()->create(['area_id' => $this->area->id]);
        $this->riesgo->planesAccion()->attach($plan->id);

        $modal = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $this->riesgo->id, 'validar')
            ->assertSee('Falta estimar el valor del plan')
            ->call('toggleSeleccion', 'plan:'.$plan->id);

        $this->assertFalse($modal->get('seleccionados')['plan:'.$plan->id]);
        $this->assertNotEmpty($modal->get('impedimentos'));
        $this->assertFalse($modal->instance()->puedeConfirmar());
    }

    #[Test]
    public function un_plan_sin_estimacion_como_entidad_principal_muestra_el_impedimento(): void
    {
        $plan = PlanAccion::factory()->sinValorar()->create(['area_id' => $this->area->id]);

        $modal = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'plan', $plan->id, 'validar')
            ->assertSee('Falta estimar el valor del plan');

        $this->assertFalse($modal->instance()->puedeConfirmar());
    }

    #[Test]
    public function confirmar_con_impedimentos_no_cambia_el_estado(): void
    {
        $riesgo = $this->riesgoSinObjetivo();

        Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar')
            ->call('confirmar')
            ->assertNoRedirect()
            ->assertSet('abierto', true);

        $this->assertEquals('borrador', $riesgo->fresh()->estado->nombre);
    }
}
