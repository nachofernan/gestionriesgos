<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\Conversacion;
use App\Livewire\Auditoria\Tarea\Show\FichaTarea;
use App\Models\Auditoria\Area;
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
 * Rediseño de tarea/show: la Ficha se edita en el bloque (nombre, descripción,
 * fecha límite, avance) por el mismo ciclo de Actualizaciones. Un avance
 * propuesto no mueve el avance del plan ni el residual del riesgo hasta
 * aplicarse.
 */
class FichaTareaTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $empleado;

    private User $comite;

    private Tarea $tarea;

    private Riesgo $riesgo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        // Riesgo total 16, con un plan aprobado (mitigación 6) de una sola tarea aprobada al 80%.
        $this->riesgo = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id, 'impacto' => 8, 'probabilidad' => 8]);
        $this->tarea = Tarea::factory()->create([
            'nombre' => 'Relevar proveedores',
            'fecha' => '2026-11-30',
            'porcentaje_avance' => 80,
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerencia->id,
        ]);
        $plan = PlanAccion::factory()->create(['estado_id' => Estado::aprobado()->id, 'area_id' => $this->gerencia->id]);
        $plan->tareas()->attach($this->tarea->id);
        $this->riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 6]);
    }

    private function residual(): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->findOrFail($this->riesgo->id)->valor_residual;
    }

    #[Test]
    public function un_empleado_propone_avance_y_no_cuenta_hasta_aplicarse(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(FichaTarea::class, ['tarea' => $this->tarea])
            ->call('activarEdicion')
            ->assertSet('form.fecha', '2026-11-30')
            ->set('form.porcentaje_avance', 100)
            ->set('mensaje', 'Relevamiento terminado')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('tarea-actualizado');

        $propuesta = $this->tarea->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertEquals(['porcentaje_avance' => 100], $propuesta->data['campos']);
        $this->assertEquals(80, $this->tarea->fresh()->porcentaje_avance);
        $this->assertEquals(16, $this->residual());

        $propuesta->marcarAprobada($this->comite);

        $this->assertEquals(100, $this->tarea->fresh()->porcentaje_avance);
        $this->assertEquals(10, $this->residual());
    }

    #[Test]
    public function el_avance_se_valida_entre_0_y_100(): void
    {
        Livewire::actingAs($this->comite)
            ->test(FichaTarea::class, ['tarea' => $this->tarea])
            ->call('activarEdicion')
            ->set('form.porcentaje_avance', 120)
            ->set('mensaje', 'Error de carga')
            ->call('guardar')
            ->assertHasErrors('form.porcentaje_avance');

        $this->assertEquals(80, $this->tarea->fresh()->porcentaje_avance);
    }

    #[Test]
    public function la_ficha_de_la_tarea_devuelve_403_a_un_gerente_de_otra_gerencia(): void
    {
        $ajena = Area::create(['nombre' => 'Gerencia Ajena', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $ajena->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaTarea::class, ['tarea' => $this->tarea])
            ->call('activarEdicion')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(FichaTarea::class, ['tarea' => $this->tarea])
            ->set('form', ['nombre' => 'X', 'descripcion' => null, 'fecha' => null, 'porcentaje_avance' => 100])
            ->set('mensaje', 'Intento ajeno')
            ->call('guardar')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(Conversacion::class, ['modelType' => 'tarea', 'modelId' => $this->tarea->id])
            ->set('mensaje', 'Nota ajena')
            ->call('enviar')
            ->assertForbidden();

        $this->assertCount(0, $this->tarea->actualizaciones()->get());
        $this->assertEquals(80, $this->tarea->fresh()->porcentaje_avance);
    }
}
