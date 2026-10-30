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
 * El número del control sale de sus checks y es el tope de cada relación (D-040).
 * Si baja, las relaciones que lo pasan se recortan. Con "aplicar también a los
 * riesgos asociados", todas toman el número nuevo, incluidos riesgos de otras
 * gerencias. En los dos casos, cada riesgo afectado recibe una entrada en su
 * historial. Si el cambio queda como propuesta, nada se toca hasta que se aplica.
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

    private Riesgo $riesgoC;

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
        $this->riesgoC = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id] + $valores);
        $this->riesgoAjeno = Riesgo::factory()->aprobado()->create(['area_id' => $this->otraGerencia->id] + $valores);

        $this->control = Control::factory()->create([
            'nombre' => 'Control de stock',
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
            'checks' => $this->checks(3), // 6
        ]);
        $this->riesgoA->controles()->attach($this->control->id, ['mitigacion' => 6]);
        $this->riesgoB->controles()->attach($this->control->id, ['mitigacion' => 4]);
        $this->riesgoC->controles()->attach($this->control->id, ['mitigacion' => 2]);
        $this->riesgoAjeno->controles()->attach($this->control->id, ['mitigacion' => null]);
    }

    /** Los primeros $n checks: valen $n × 2. */
    private function checks(int $n): array
    {
        return array_slice(array_keys(Control::CHECKS), 0, $n);
    }

    /** Campos de un cambio de checks, como los arma FichaControl::guardar(). */
    private function cambioDeChecks(int $n): array
    {
        return ['checks' => $this->checks($n), 'mitigacion_default' => $n * 2];
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

    private function entradas(Riesgo $riesgo, string $origen)
    {
        return $riesgo->actualizaciones()->where('data->origen', $origen)->get();
    }

    #[Test]
    public function el_numero_del_control_sale_de_sus_checks(): void
    {
        $this->assertSame(6, $this->control->fresh()->mitigacion_default);

        $this->control->update(['checks' => ['deja_evidencia', 'inventado', 'deja_evidencia']]);

        $this->assertSame(['deja_evidencia'], $this->control->fresh()->checks);
        $this->assertSame(2, $this->control->fresh()->mitigacion_default);
    }

    #[Test]
    public function bajar_el_numero_del_control_recorta_las_relaciones_que_lo_pasan(): void
    {
        Actualizacion::registrarCambioCampos($this->control, $this->comite, 'Baja', $this->cambioDeChecks(2)); // 6 → 4

        $this->assertSame(4, $this->control->fresh()->mitigacion_default);
        $this->assertSame(4, $this->mitigacionEn($this->riesgoA));
        $this->assertSame(4, $this->mitigacionEn($this->riesgoB));
        $this->assertSame(2, $this->mitigacionEn($this->riesgoC));
        // Sin número propio sigue heredando: no se toca, mitiga el nuevo.
        $this->assertNull($this->mitigacionEn($this->riesgoAjeno));
        $this->assertSame(8, $this->residual($this->riesgoAjeno));

        $entrada = $this->entradas($this->riesgoA, 'tope_control')->sole();
        $this->assertEquals(Estado::aprobado()->id, $entrada->estado_id);
        $this->assertEquals(
            [['id' => $this->control->id, 'nombre' => 'Control de stock', 'mitigacion_antes' => 6, 'mitigacion_despues' => 4]],
            $entrada->data['diff']['relaciones']['controles']['cambia']
        );
        $this->assertCount(0, $this->entradas($this->riesgoB, 'tope_control'));
        $this->assertCount(0, $this->entradas($this->riesgoC, 'tope_control'));
    }

    #[Test]
    public function subir_el_numero_del_control_no_toca_las_relaciones(): void
    {
        Actualizacion::registrarCambioCampos($this->control, $this->comite, 'Sube', $this->cambioDeChecks(5)); // 6 → 10

        $this->assertSame(10, $this->control->fresh()->mitigacion_default);
        $this->assertSame(6, $this->mitigacionEn($this->riesgoA));
        $this->assertSame(4, $this->mitigacionEn($this->riesgoB));
        $this->assertSame(2, $this->residual($this->riesgoAjeno)); // hereda 10
        $this->assertCount(0, $this->entradas($this->riesgoA, 'tope_control'));
    }

    #[Test]
    public function cambiar_el_numero_tildado_pisa_todas_las_asociaciones_y_baja_el_residual(): void
    {
        Actualizacion::registrarCambioCampos($this->control, $this->comite, 'Baja', $this->cambioDeChecks(2), true);

        foreach ([$this->riesgoA, $this->riesgoB, $this->riesgoC, $this->riesgoAjeno] as $riesgo) {
            $this->assertSame(4, $this->mitigacionEn($riesgo));
            $this->assertSame(8, $this->residual($riesgo));
        }
        $this->assertSame($this->checks(2), $this->riesgoC->controles()->first()->pivot->checks);
    }

    #[Test]
    public function la_propagacion_deja_una_actualizacion_aplicada_en_cada_riesgo_afectado(): void
    {
        Actualizacion::registrarCambioCampos($this->control, $this->comite, 'Sube', $this->cambioDeChecks(4), true); // 6 → 8

        $entrada = $this->entradas($this->riesgoA, 'default_control')->sole();
        $this->assertEquals(Estado::aprobado()->id, $entrada->estado_id);
        $this->assertEquals($this->comite->id, $entrada->user_id);
        $this->assertEquals(
            [['id' => $this->control->id, 'nombre' => 'Control de stock', 'mitigacion_antes' => 6, 'mitigacion_despues' => 8]],
            $entrada->data['diff']['relaciones']['controles']['cambia']
        );
        $this->assertCount(0, $this->riesgoA->actualizaciones()->propuestasPendientes()->get());

        // El pivot null usaba el número viejo (6): el historial lo cuenta así.
        $this->assertEquals(6, $this->entradas($this->riesgoAjeno, 'default_control')->sole()->data['diff']['relaciones']['controles']['cambia'][0]['mitigacion_antes']);
        $this->assertCount(1, $this->entradas($this->riesgoC, 'default_control'));
    }

    #[Test]
    public function como_propuesta_no_recorta_hasta_que_se_aplica(): void
    {
        $this->control->update(['estado_id' => Estado::validado()->id]);

        $propuesta = Actualizacion::registrarCambioCampos($this->control, $this->empleado, 'Baja', $this->cambioDeChecks(1));

        $this->assertSame(6, $this->control->fresh()->mitigacion_default);
        $this->assertSame(6, $this->mitigacionEn($this->riesgoA));
        $this->assertSame('Documentado en un procedimiento', $propuesta->data['diff']['campos']['checks']['despues']);

        $propuesta->marcarValidada($this->gerente);

        $this->assertSame(2, $this->control->fresh()->mitigacion_default);
        $this->assertSame(2, $this->mitigacionEn($this->riesgoA));
        $this->assertSame(2, $this->mitigacionEn($this->riesgoB));
        $this->assertEquals($this->gerente->id, $this->entradas($this->riesgoA, 'tope_control')->sole()->user_id);
    }

    #[Test]
    public function como_propuesta_no_propaga_hasta_que_se_aplica(): void
    {
        $this->control->update(['estado_id' => Estado::validado()->id]);

        $propuesta = Actualizacion::registrarCambioCampos($this->control, $this->empleado, 'Sube', $this->cambioDeChecks(4), true);

        $this->assertTrue($propuesta->data['propagar_mitigacion']);
        $this->assertSame(4, $this->mitigacionEn($this->riesgoB));

        $propuesta->marcarValidada($this->gerente);

        $this->assertSame(8, $this->control->fresh()->mitigacion_default);
        $this->assertSame(8, $this->mitigacionEn($this->riesgoB));
        $this->assertEquals($this->gerente->id, $this->entradas($this->riesgoB, 'default_control')->sole()->user_id);
    }

    #[Test]
    public function aprobar_el_control_aplica_la_propagacion_de_sus_actualizaciones_pendientes(): void
    {
        $this->control->update(['estado_id' => Estado::validado()->id]);
        // Gerente sobre un control validado: nace validada y se aplica en el acto,
        // así que se arma a mano la validada que espera la aprobación del control.
        $this->control->actualizaciones()->create([
            'user_id' => $this->gerente->id,
            'mensaje' => 'Sube',
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'cambio', 'campos' => $this->cambioDeChecks(4), 'propagar_mitigacion' => true],
        ]);

        $this->actingAs($this->comite)->post(route('auditoria.controles.aprobar', $this->control))->assertRedirect();

        $this->assertSame(8, $this->control->fresh()->mitigacion_default);
        $this->assertSame(8, $this->mitigacionEn($this->riesgoB));
        $this->assertSame(8, $this->mitigacionEn($this->riesgoC));
    }

    #[Test]
    public function el_alta_calcula_el_numero_desde_los_checks(): void
    {
        $this->actingAs($this->gerente)->post(route('auditoria.controles.store'), [
            'nombre' => 'Control nuevo',
            'checks' => ['documentado', 'deja_evidencia'],
            'area_id' => $this->gerencia->id,
        ])->assertRedirect();

        $control = Control::where('nombre', 'Control nuevo')->sole();
        $this->assertSame(4, $control->mitigacion_default);
        $this->assertSame(['documentado', 'deja_evidencia'], $control->checks);
    }

    #[Test]
    public function el_alta_rechaza_un_check_que_no_existe(): void
    {
        $this->actingAs($this->gerente)->post(route('auditoria.controles.store'), [
            'nombre' => 'Control nuevo',
            'checks' => ['inventado'],
            'area_id' => $this->gerencia->id,
        ])->assertSessionHasErrors('checks.0');

        $this->assertFalse(Control::where('nombre', 'Control nuevo')->exists());
    }

    #[Test]
    public function editar_un_borrador_sin_propagar_recorta_y_con_propagar_pisa(): void
    {
        $this->control->update(['estado_id' => Estado::borrador()->id]);
        $datos = ['nombre' => 'Control de stock', 'checks' => $this->checks(2), 'area_id' => $this->gerencia->id];

        $this->actingAs($this->gerente)->put(route('auditoria.controles.update', $this->control), $datos)->assertRedirect();
        $this->assertSame(4, $this->mitigacionEn($this->riesgoA));
        $this->assertSame(2, $this->mitigacionEn($this->riesgoC));

        $datos['checks'] = $this->checks(1);
        $this->actingAs($this->gerente)
            ->put(route('auditoria.controles.update', $this->control), $datos + ['propagar_mitigacion' => '1'])
            ->assertRedirect();
        $this->assertSame(2, $this->mitigacionEn($this->riesgoB));
        $this->assertSame(2, $this->mitigacionEn($this->riesgoAjeno));

        $edicion = $this->control->actualizaciones()->where('data->tipo', 'edicion')->latest('id')->first();
        $this->assertEquals(['antes' => 4, 'despues' => 2], $edicion->data['diff']['campos']['mitigacion_default']);
    }

    #[Test]
    public function editar_un_borrador_ajeno_devuelve_403_y_no_toca_nada(): void
    {
        $this->control->update(['estado_id' => Estado::borrador()->id]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->otraGerencia->id]);

        $this->actingAs($ajeno)
            ->put(route('auditoria.controles.update', $this->control), [
                'nombre' => 'X', 'checks' => $this->checks(1), 'propagar_mitigacion' => '1',
            ])
            ->assertForbidden();

        $this->assertSame(6, $this->control->fresh()->mitigacion_default);
        $this->assertSame(6, $this->mitigacionEn($this->riesgoA));
    }

    #[Test]
    public function la_ficha_del_control_propaga_la_mitigacion_si_se_tilda(): void
    {
        Livewire::actingAs($this->comite)
            ->test(FichaControl::class, ['control' => $this->control])
            ->call('activarEdicion')
            ->set('form.checks', $this->checks(2))
            ->assertViewHas('puntaje', 4)
            ->assertViewHas('afectados', fn ($a) => $a->count() === 4)
            ->set('propagar', true)
            ->set('mensaje', 'Se revisó el control')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('control-actualizado');

        $this->assertSame(4, $this->control->fresh()->mitigacion_default);
        $this->assertSame(4, $this->mitigacionEn($this->riesgoC));
    }

    #[Test]
    public function la_ficha_del_control_sin_tildar_recorta(): void
    {
        Livewire::actingAs($this->comite)
            ->test(FichaControl::class, ['control' => $this->control])
            ->call('activarEdicion')
            ->set('form.checks', $this->checks(2))
            ->set('mensaje', 'Se revisó el control')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(4, $this->control->fresh()->mitigacion_default);
        $this->assertSame(4, $this->mitigacionEn($this->riesgoA));
        $this->assertSame(2, $this->mitigacionEn($this->riesgoC));
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
            ->set('form', ['nombre' => 'X', 'descripcion' => null, 'checks' => $this->checks(1)])
            ->set('propagar', true)
            ->set('mensaje', 'Intento ajeno')
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame(6, $this->control->fresh()->mitigacion_default);
        $this->assertSame(6, $this->mitigacionEn($this->riesgoA));
    }
}
