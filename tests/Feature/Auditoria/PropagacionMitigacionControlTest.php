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
 * Cambiar la mitigación por defecto de un control con "aplicar también a los
 * riesgos asociados" pisa la mitigación de todas sus asociaciones —incluidos
 * riesgos compartidos y de otras gerencias— y deja constancia en el historial de
 * cada riesgo afectado. Sin la opción, sólo cambia el default. Si el cambio queda
 * como propuesta, la propagación ocurre recién cuando se aplica.
 */
class PropagacionMitigacionControlTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private Area $otraGerencia;

    private User $gerente;

    private User $empleado;

    private User $comite;

    private Riesgo $riesgoA;

    private Riesgo $riesgoB;

    private Riesgo $riesgoCompartido;

    private Riesgo $riesgoAjeno;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->otraGerencia = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        // Total 12 en todos.
        $valores = ['impacto' => 6, 'probabilidad' => 6];
        $this->riesgoA = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id] + $valores);
        $this->riesgoB = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id] + $valores);
        $this->riesgoCompartido = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id] + $valores);
        $this->riesgoCompartido->areas()->syncWithoutDetaching([$this->otraGerencia->id]);
        $this->riesgoAjeno = Riesgo::factory()->aprobado()->create(['area_id' => $this->otraGerencia->id] + $valores);

        $this->control = Control::factory()->create([
            'nombre' => 'Control de stock',
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
            'mitigacion_default' => 3,
        ]);
        $this->riesgoA->controles()->attach($this->control->id, ['mitigacion' => 3]);
        $this->riesgoB->controles()->attach($this->control->id, ['mitigacion' => 5]);
        $this->riesgoCompartido->controles()->attach($this->control->id, ['mitigacion' => 4]);
        $this->riesgoAjeno->controles()->attach($this->control->id, ['mitigacion' => null]);
    }

    private function mitigacionEn(Riesgo $riesgo): ?int
    {
        return $riesgo->controles()->whereKey($this->control->id)->first()->pivot->mitigacion;
    }

    private function residual(Riesgo $riesgo): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->findOrFail($riesgo->id)->valor_residual;
    }

    private function entradasDePropagacion(Riesgo $riesgo)
    {
        return $riesgo->actualizaciones()->where('data->origen', 'default_control')->get();
    }

    #[Test]
    public function cambiar_el_default_sin_tildar_no_toca_las_asociaciones(): void
    {
        Actualizacion::registrarCambioCampos($this->control, $this->comite, 'Baja', ['mitigacion_default' => 2]);

        $this->assertEquals(2, $this->control->fresh()->mitigacion_default);
        $this->assertEquals(3, $this->mitigacionEn($this->riesgoA));
        $this->assertEquals(5, $this->mitigacionEn($this->riesgoB));
        $this->assertEquals(4, $this->mitigacionEn($this->riesgoCompartido));
        $this->assertEquals(7, $this->residual($this->riesgoB));
        $this->assertCount(0, $this->entradasDePropagacion($this->riesgoB));
    }

    #[Test]
    public function cambiar_el_default_tildado_pisa_todas_las_asociaciones_y_baja_el_residual(): void
    {
        Actualizacion::registrarCambioCampos($this->control, $this->comite, 'Baja', ['mitigacion_default' => 2], true);

        foreach ([$this->riesgoA, $this->riesgoB, $this->riesgoCompartido, $this->riesgoAjeno] as $riesgo) {
            $this->assertEquals(2, $this->mitigacionEn($riesgo));
            $this->assertEquals(10, $this->residual($riesgo));
        }
    }

    #[Test]
    public function la_propagacion_deja_una_actualizacion_aplicada_en_cada_riesgo_afectado(): void
    {
        Actualizacion::registrarCambioCampos($this->control, $this->comite, 'Sube', ['mitigacion_default' => 5], true);

        // B ya valía 5: no cambia nada, no deja entrada.
        $this->assertCount(0, $this->entradasDePropagacion($this->riesgoB));

        $entrada = $this->entradasDePropagacion($this->riesgoA)->sole();
        $this->assertEquals(Estado::aprobado()->id, $entrada->estado_id);
        $this->assertEquals($this->comite->id, $entrada->user_id);
        $this->assertEquals(
            [['id' => $this->control->id, 'nombre' => 'Control de stock', 'mitigacion_antes' => 3, 'mitigacion_despues' => 5]],
            $entrada->data['diff']['relaciones']['controles']['cambia']
        );
        $this->assertCount(0, $this->riesgoA->actualizaciones()->propuestasPendientes()->get());

        // El pivot null usaba el default viejo (3): el historial lo cuenta así.
        $this->assertEquals(3, $this->entradasDePropagacion($this->riesgoAjeno)->sole()->data['diff']['relaciones']['controles']['cambia'][0]['mitigacion_antes']);
        $this->assertCount(1, $this->entradasDePropagacion($this->riesgoCompartido));
    }

    #[Test]
    public function como_propuesta_no_propaga_hasta_que_se_aplica(): void
    {
        $this->control->update(['estado_id' => Estado::validado()->id]);

        $propuesta = Actualizacion::registrarCambioCampos($this->control, $this->empleado, 'Baja', ['mitigacion_default' => 2], true);

        $this->assertTrue($propuesta->data['propagar_mitigacion']);
        $this->assertEquals(3, $this->control->fresh()->mitigacion_default);
        $this->assertEquals(5, $this->mitigacionEn($this->riesgoB));

        $propuesta->marcarValidada($this->gerente);

        $this->assertEquals(2, $this->control->fresh()->mitigacion_default);
        $this->assertEquals(2, $this->mitigacionEn($this->riesgoB));
        $this->assertEquals($this->gerente->id, $this->entradasDePropagacion($this->riesgoB)->sole()->user_id);
    }

    #[Test]
    public function aprobar_el_control_aplica_la_propagacion_de_sus_actualizaciones_pendientes(): void
    {
        $this->control->update(['estado_id' => Estado::validado()->id]);
        // Gerente sobre un control validado: nace validada y se aplica en el acto,
        // así que se arma a mano la validada que espera la aprobación del control.
        $this->control->actualizaciones()->create([
            'user_id' => $this->gerente->id,
            'mensaje' => 'Baja',
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['mitigacion_default' => 2], 'propagar_mitigacion' => true],
        ]);

        $this->actingAs($this->comite)->post(route('auditoria.controles.aprobar', $this->control))->assertRedirect();

        $this->assertEquals(2, $this->control->fresh()->mitigacion_default);
        $this->assertEquals(2, $this->mitigacionEn($this->riesgoB));
        $this->assertEquals(2, $this->mitigacionEn($this->riesgoCompartido));
    }

    #[Test]
    public function editar_un_borrador_con_propagar_pisa_las_asociaciones(): void
    {
        $this->control->update(['estado_id' => Estado::borrador()->id]);
        $datos = ['nombre' => 'Control de stock', 'mitigacion_default' => 2, 'area_id' => $this->gerencia->id];

        $this->actingAs($this->gerente)->put(route('auditoria.controles.update', $this->control), $datos)->assertRedirect();
        $this->assertEquals(5, $this->mitigacionEn($this->riesgoB));

        $datos['mitigacion_default'] = 1;
        $this->actingAs($this->gerente)
            ->put(route('auditoria.controles.update', $this->control), $datos + ['propagar_mitigacion' => '1'])
            ->assertRedirect();
        $this->assertEquals(1, $this->mitigacionEn($this->riesgoB));
        $this->assertEquals(1, $this->mitigacionEn($this->riesgoAjeno));
    }

    #[Test]
    public function editar_un_borrador_ajeno_con_propagar_devuelve_403_y_no_toca_nada(): void
    {
        $this->control->update(['estado_id' => Estado::borrador()->id]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->otraGerencia->id]);

        $this->actingAs($ajeno)
            ->put(route('auditoria.controles.update', $this->control), [
                'nombre' => 'X', 'mitigacion_default' => 1, 'propagar_mitigacion' => '1',
            ])
            ->assertForbidden();

        $this->assertEquals(5, $this->mitigacionEn($this->riesgoB));
    }

    #[Test]
    public function la_ficha_del_control_propaga_la_mitigacion_si_se_tilda(): void
    {
        Livewire::actingAs($this->comite)
            ->test(FichaControl::class, ['control' => $this->control])
            ->call('activarEdicion')
            ->set('form.mitigacion_default', 2)
            ->assertViewHas('ocultos', 0)
            ->assertViewHas('afectados', fn ($a) => $a->count() === 4)
            ->set('propagar', true)
            ->set('mensaje', 'Se revisó el control')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('control-actualizado');

        $this->assertEquals(2, $this->control->fresh()->mitigacion_default);
        $this->assertEquals(2, $this->mitigacionEn($this->riesgoB));
    }

    #[Test]
    public function la_ficha_del_control_sin_tildar_solo_cambia_el_default(): void
    {
        Livewire::actingAs($this->comite)
            ->test(FichaControl::class, ['control' => $this->control])
            ->call('activarEdicion')
            ->set('form.mitigacion_default', 2)
            ->set('mensaje', 'Se revisó el control')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertEquals(2, $this->control->fresh()->mitigacion_default);
        $this->assertEquals(5, $this->mitigacionEn($this->riesgoB));
    }

    #[Test]
    public function la_ficha_del_control_devuelve_403_a_un_gerente_de_otra_gerencia(): void
    {
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->otraGerencia->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaControl::class, ['control' => $this->control])
            ->call('activarEdicion')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(FichaControl::class, ['control' => $this->control])
            ->set('form', ['nombre' => 'X', 'descripcion' => null, 'mitigacion_default' => 1])
            ->set('propagar', true)
            ->set('mensaje', 'Intento ajeno')
            ->call('guardar')
            ->assertForbidden();

        $this->assertEquals(3, $this->control->fresh()->mitigacion_default);
        $this->assertEquals(5, $this->mitigacionEn($this->riesgoB));
    }
}
