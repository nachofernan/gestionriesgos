<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Riesgo\Show\GestionControles;
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
 * Efectividad de cada relación control-riesgo (D-040): regula cuánto del número
 * de la relación (o del mitigacion_default si no tiene uno propio) descuenta del
 * residual. floor(número × efectividad / 100), por relación. También cubre el
 * puntaje de los checks del control.
 */
class EfectividadRelacionTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
    }

    private function control(int $default = 4, string $estado = 'aprobado'): Control
    {
        return Control::factory()->create([
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
            'mitigacion_default' => $default,
        ]);
    }

    /** Riesgo de total 16. */
    private function riesgo(): Riesgo
    {
        return Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8, 'area_id' => $this->gerencia->id]);
    }

    private function riesgoConControl(int $efectividad, ?int $numero, int $default = 4, string $estado = 'aprobado'): Riesgo
    {
        $riesgo = $this->riesgo();
        $riesgo->controles()->attach($this->control($default, $estado)->id, ['mitigacion' => $numero, 'efectividad' => $efectividad]);

        return $riesgo;
    }

    private function residual(Riesgo $riesgo): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->findOrFail($riesgo->id)->valor_residual;
    }

    #[Test]
    public function una_relacion_nueva_nace_con_efectividad_100(): void
    {
        $riesgo = $this->riesgo();
        $riesgo->controles()->attach($this->control()->id, ['mitigacion' => 4]);

        $this->assertSame(100, $riesgo->controles()->first()->pivot->efectividad);
    }

    #[Test]
    public function una_relacion_al_100_por_ciento_mitiga_su_numero_completo(): void
    {
        $this->assertEquals(12, $this->residual($this->riesgoConControl(100, 4))); // 16 - 4
    }

    #[Test]
    public function la_relacion_al_50_por_ciento_mitiga_la_mitad(): void
    {
        $riesgo = $this->riesgoConControl(50, 4);

        $this->assertEquals(2, Riesgo::with('controles.estado')->find($riesgo->id)->mitigacion_controles);
        $this->assertEquals(14, $this->residual($riesgo)); // 16 - 2
    }

    #[Test]
    public function una_efectividad_baja_puede_dejar_la_mitigacion_en_cero(): void
    {
        $this->assertEquals(16, $this->residual($this->riesgoConControl(10, 4))); // 4 × 10% = 0,4 → 0
    }

    #[Test]
    public function una_relacion_al_99_no_mitiga_el_valor_entero(): void
    {
        $this->assertEquals(13, $this->residual($this->riesgoConControl(99, 4))); // 3,96 → 3
    }

    #[Test]
    public function la_mitigacion_efectiva_se_redondea_por_relacion(): void
    {
        $riesgo = $this->riesgoConControl(50, 3); // 1,5 → 1
        $riesgo->controles()->attach($this->control(3)->id, ['mitigacion' => 3, 'efectividad' => 50]);

        // 1 + 1 = 2, no floor(1,5 + 1,5) = 3.
        $this->assertEquals(14, $this->residual($riesgo));
    }

    #[Test]
    public function dos_relaciones_del_mismo_control_mitigan_segun_su_propia_efectividad(): void
    {
        $control = $this->control(6);
        $uno = $this->riesgo();
        $otro = $this->riesgo();
        $uno->controles()->attach($control->id, ['mitigacion' => 6, 'efectividad' => 100]);
        $otro->controles()->attach($control->id, ['mitigacion' => 6, 'efectividad' => 50]);

        $this->assertEquals(10, $this->residual($uno)); // 16 - 6
        $this->assertEquals(13, $this->residual($otro)); // 16 - 3
    }

    #[Test]
    public function sin_numero_propio_la_relacion_hereda_el_default_del_control(): void
    {
        $this->assertEquals(13, $this->residual($this->riesgoConControl(50, 6, default: 2))); // 6 × 50% = 3
        $this->assertEquals(15, $this->residual($this->riesgoConControl(50, null, default: 2))); // 2 × 50% = 1
    }

    #[Test]
    public function un_control_en_borrador_no_mitiga_aunque_la_relacion_este_al_100(): void
    {
        $this->assertEquals(16, $this->residual($this->riesgoConControl(100, 4, estado: 'borrador')));
    }

    #[Test]
    public function la_preview_de_gestion_controles_cuadra_con_el_residual_del_modelo(): void
    {
        $riesgo = $this->riesgoConControl(50, 4); // residual 14
        $nuevo = $this->control(5);

        Livewire::actingAs(User::factory()->create(['rol' => 'comite', 'area_id' => null]))
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $nuevo->id)
            ->assertDispatched('residual-actualizado', valor: 9); // 16 - 2 - 5 (el nuevo nace al 100%)
    }

    #[Test]
    public function quitar_y_volver_a_agregar_un_control_conserva_su_efectividad(): void
    {
        $riesgo = $this->riesgoConControl(50, 4);
        $controlId = $riesgo->controles()->first()->id;

        Livewire::actingAs(User::factory()->create(['rol' => 'comite', 'area_id' => null]))
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $controlId)
            ->call('agregar', $controlId)
            ->assertDispatched('residual-actualizado', valor: 14)
            ->call('guardar');

        $this->assertSame(50, $riesgo->controles()->first()->pivot->efectividad);
        $this->assertEquals(14, $this->residual($riesgo));
    }

    #[Test]
    public function el_puntaje_suma_dos_por_cada_check_valido(): void
    {
        $this->assertSame(0, Control::puntajeChecks(null));
        $this->assertSame(4, Control::puntajeChecks(['documentado', 'deja_evidencia']));
        $this->assertSame(10, Control::puntajeChecks(array_keys(Control::CHECKS)));
        // Las claves desconocidas o repetidas no suman.
        $this->assertSame(2, Control::puntajeChecks(['documentado', 'documentado', 'inventado']));
    }
}
