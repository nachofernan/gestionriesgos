<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Control\Show\FichaControl;
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
 * El valor de un control es suyo y vale igual en cada riesgo que mitiga (D-051):
 * la relación no tiene número propio, así que cambiar el valor del control mueve
 * el residual de todos sus riesgos a la vez, incluidos los de otras gerencias, y
 * sin dejar entradas en el historial de cada riesgo. Si el cambio queda como
 * propuesta, nada se mueve hasta que se aplica.
 */
class ValorControlTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private Area $otraGerencia;

    private User $gerente;

    private User $empleado;

    private User $comite;

    private Riesgo $riesgo;

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

        // Total 12 en los dos.
        $valores = ['impacto' => 6, 'probabilidad' => 6];
        $this->riesgo = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id] + $valores);
        $this->riesgoAjeno = Riesgo::factory()->aprobado()->create(['area_id' => $this->otraGerencia->id] + $valores);

        $this->control = Control::factory()->create([
            'nombre' => 'Control de stock',
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
            'checks' => $this->checks(3), // 6
        ]);
        $this->riesgo->controles()->attach($this->control->id);
        $this->riesgoAjeno->controles()->attach($this->control->id);
    }

    /** Los primeros $n checks: valen $n × 2. */
    private function checks(int $n): array
    {
        return array_slice(array_keys(Control::CHECKS), 0, $n);
    }

    private function residual(Riesgo $riesgo): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->findOrFail($riesgo->id)->valor_residual;
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
    public function cambiar_el_valor_del_control_lo_cambia_en_todos_sus_riesgos(): void
    {
        $this->assertSame(6, $this->residual($this->riesgo));
        $this->assertSame(6, $this->residual($this->riesgoAjeno));

        Livewire::actingAs($this->comite)
            ->test(FichaControl::class, ['control' => $this->control])
            ->call('activarEdicion')
            ->set('form.checks', $this->checks(2))
            ->assertViewHas('puntaje', 4)
            ->set('mensaje', 'Se revisó el control')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('control-actualizado');

        $this->assertSame(4, $this->control->fresh()->mitigacion_default);
        $this->assertSame(8, $this->residual($this->riesgo));
        $this->assertSame(8, $this->residual($this->riesgoAjeno));
        // El cambio queda en el historial del control, no en el de cada riesgo.
        $this->assertSame(0, $this->riesgo->actualizaciones()->count());
    }

    #[Test]
    public function como_propuesta_el_valor_no_cambia_hasta_que_se_aplica(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(FichaControl::class, ['control' => $this->control])
            ->call('activarEdicion')
            ->set('form.checks', $this->checks(1))
            ->set('mensaje', 'Propongo bajarlo')
            ->call('guardar')
            ->assertHasNoErrors();

        $propuesta = $this->control->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertSame(6, $this->control->fresh()->mitigacion_default);
        $this->assertSame(6, $this->residual($this->riesgo));

        $propuesta->aplicarCambios($this->gerente);

        $this->assertSame(2, $this->control->fresh()->mitigacion_default);
        $this->assertSame(10, $this->residual($this->riesgo));
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
    public function editar_un_borrador_ajeno_devuelve_403_y_no_toca_nada(): void
    {
        $this->control->update(['estado_id' => Estado::borrador()->id]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->otraGerencia->id]);

        $this->actingAs($ajeno)
            ->put(route('auditoria.controles.update', $this->control), ['nombre' => 'X', 'checks' => $this->checks(1)])
            ->assertForbidden();

        $this->assertSame(6, $this->control->fresh()->mitigacion_default);
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
            ->set('mensaje', 'Intento ajeno')
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame(6, $this->control->fresh()->mitigacion_default);
    }
}
