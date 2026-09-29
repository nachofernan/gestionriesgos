<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Control\Show\FichaControl;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un control pausado deja de descontar del residual de sus riesgos, sin perder la
 * asociación ni su mitigación; al reanudarlo vuelve a descontar. Pausar y reanudar
 * es un campo más de la ficha: pasa por el ciclo de propuestas y exige autorización.
 */
class PausaControlTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private Area $otraGerencia;

    private User $empleado;

    private User $comite;

    private Riesgo $riesgo;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->otraGerencia = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        $this->riesgo = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id, 'impacto' => 6, 'probabilidad' => 6]);
        $this->control = Control::factory()->create([
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
            'mitigacion_default' => 4,
        ]);
        $this->riesgo->controles()->attach($this->control->id, ['mitigacion' => 5]);
    }

    private function residual(): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->findOrFail($this->riesgo->id)->valor_residual;
    }

    #[Test]
    public function un_control_pausado_no_baja_el_residual(): void
    {
        $this->assertEquals(7, $this->residual());

        $this->control->update(['pausado' => true]);

        $this->assertEquals(12, $this->residual());
        $this->assertEquals(5, $this->riesgo->controles()->first()->pivot->mitigacion);
    }

    #[Test]
    public function un_control_reanudado_vuelve_a_mitigar(): void
    {
        $this->control->update(['pausado' => true]);
        $this->control->update(['pausado' => false]);

        $this->assertEquals(7, $this->residual());
    }

    #[Test]
    public function pausar_desde_la_ficha_queda_como_propuesta_y_recien_al_aprobarla_deja_de_mitigar(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(FichaControl::class, ['control' => $this->control])
            ->call('activarEdicion')
            ->set('form.pausado', true)
            ->set('mensaje', 'Se pausa mientras se rediseña')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertFalse($this->control->fresh()->pausado);
        $this->assertEquals(7, $this->residual());

        $propuesta = $this->control->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertEquals(['antes' => false, 'despues' => true], $propuesta->data['diff']['campos']['pausado']);

        $propuesta->marcarAprobada($this->comite);

        $this->assertTrue($this->control->fresh()->pausado);
        $this->assertEquals(12, $this->residual());
    }

    #[Test]
    public function la_ficha_del_control_devuelve_403_a_un_gerente_de_otra_gerencia_al_pausar(): void
    {
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->otraGerencia->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaControl::class, ['control' => $this->control])
            ->set('form', ['nombre' => 'X', 'descripcion' => null, 'mitigacion_default' => 4, 'pausado' => true])
            ->set('mensaje', 'Intento')
            ->call('guardar')
            ->assertForbidden();

        $this->assertFalse($this->control->fresh()->pausado);
        $this->assertEquals(0, Actualizacion::where('actualizable_id', $this->control->id)->where('data->tipo', 'cambio')->count());
    }
}
