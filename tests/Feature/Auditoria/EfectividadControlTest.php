<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Control\Show\FichaControl;
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
 * Efectividad del control (D-036): regula cuánto de su mitigación nominal (pivot
 * o mitigacion_default) descuenta del residual. round(nominal × efectividad / 100),
 * por cada par control-riesgo. Se cambia como cualquier campo del control.
 */
class EfectividadControlTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
    }

    /** Riesgo de total 16 con un control aprobado asociado. */
    private function riesgoConControl(int $efectividad, ?int $pivot, int $default = 4, string $estado = 'aprobado'): Riesgo
    {
        $riesgo = Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8, 'area_id' => $this->gerencia->id]);
        $control = Control::factory()->create([
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
            'mitigacion_default' => $default,
            'efectividad' => $efectividad,
        ]);
        $riesgo->controles()->attach($control->id, ['mitigacion' => $pivot]);

        return $riesgo;
    }

    private function residual(Riesgo $riesgo): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->findOrFail($riesgo->id)->valor_residual;
    }

    #[Test]
    public function un_control_nuevo_arranca_con_efectividad_100(): void
    {
        $control = Control::factory()->create();

        $this->assertEquals(100, $control->fresh()->efectividad);
    }

    #[Test]
    public function un_control_al_100_por_ciento_mitiga_su_valor_completo(): void
    {
        $this->assertEquals(12, $this->residual($this->riesgoConControl(100, 4))); // 16 - 4
    }

    #[Test]
    public function la_efectividad_al_50_por_ciento_reduce_la_mitigacion_a_la_mitad(): void
    {
        $riesgo = $this->riesgoConControl(50, 4);

        $cargado = Riesgo::with('controles.estado')->find($riesgo->id);
        $this->assertEquals(2, $cargado->mitigacion_controles);
        $this->assertEquals(14, $this->residual($riesgo)); // 16 - 2
    }

    #[Test]
    public function una_efectividad_baja_puede_dejar_la_mitigacion_en_cero(): void
    {
        $this->assertEquals(16, $this->residual($this->riesgoConControl(10, 4))); // 4 × 10% = 0,4 → 0
    }

    #[Test]
    public function la_mitigacion_efectiva_se_redondea_por_control(): void
    {
        $riesgo = $this->riesgoConControl(50, 3); // 1,5 → 2
        $otro = Control::factory()->create([
            'estado_id' => Estado::aprobado()->id,
            'mitigacion_default' => 3,
            'efectividad' => 50,
        ]);
        $riesgo->controles()->attach($otro->id, ['mitigacion' => 3]);

        // 2 + 2 = 4, no round(1,5 + 1,5) = 3.
        $this->assertEquals(12, $this->residual($riesgo));
    }

    #[Test]
    public function la_efectividad_se_aplica_sobre_la_mitigacion_del_pivot(): void
    {
        $this->assertEquals(13, $this->residual($this->riesgoConControl(50, 6, default: 2))); // 6 × 50% = 3
        $this->assertEquals(15, $this->residual($this->riesgoConControl(50, null, default: 2))); // sin pivot: 2 × 50% = 1
    }

    #[Test]
    public function un_control_en_borrador_no_mitiga_aunque_tenga_efectividad(): void
    {
        $this->assertEquals(16, $this->residual($this->riesgoConControl(100, 4, estado: 'borrador')));
    }

    #[Test]
    public function la_preview_de_gestion_controles_cuadra_con_el_residual_del_modelo(): void
    {
        $riesgo = $this->riesgoConControl(50, 4); // residual 14
        $nuevo = Control::factory()->create([
            'estado_id' => Estado::aprobado()->id,
            'mitigacion_default' => 3,
            'efectividad' => 50,
        ]);

        Livewire::actingAs(User::factory()->create(['rol' => 'comite', 'area_id' => null]))
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $nuevo->id)
            ->assertDispatched('residual-actualizado', valor: 12); // 16 - 2 - 2
    }

    #[Test]
    public function un_cambio_de_efectividad_en_un_control_aprobado_va_como_propuesta(): void
    {
        $riesgo = $this->riesgoConControl(100, 4); // residual 12
        $control = $riesgo->controles()->first();
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        Livewire::actingAs($gerente)
            ->test(FichaControl::class, ['control' => $control])
            ->call('activarEdicion')
            ->set('form.efectividad', 50)
            ->set('mensaje', 'La auditoría bajó su efectividad')
            ->call('guardar')
            ->assertHasNoErrors();

        // Pendiente de aprobación: todavía no baja nada.
        $this->assertEquals(100, $control->fresh()->efectividad);
        $this->assertEquals(12, $this->residual($riesgo));

        $propuesta = $control->actualizaciones()->propuestasPendientes()->sole();
        $propuesta->marcarAprobada($comite);

        $this->assertEquals(50, $control->fresh()->efectividad);
        $this->assertEquals(14, $this->residual($riesgo)); // 16 - 2
    }

    #[Test]
    public function un_gerente_de_otra_gerencia_no_puede_cambiar_la_efectividad(): void
    {
        $riesgo = $this->riesgoConControl(100, 4);
        $control = $riesgo->controles()->first();
        $otra = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $otra->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaControl::class, ['control' => $control])
            ->set('form', ['nombre' => $control->nombre, 'descripcion' => null, 'mitigacion_default' => 4, 'efectividad' => 10])
            ->set('mensaje', 'Intento ajeno')
            ->call('guardar')
            ->assertForbidden();

        $this->assertEquals(100, $control->fresh()->efectividad);
    }

    #[Test]
    public function la_efectividad_fuera_de_rango_se_rechaza(): void
    {
        $control = Control::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::aprobado()->id]);
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        foreach ([101, -1] as $valor) {
            Livewire::actingAs($comite)
                ->test(FichaControl::class, ['control' => $control])
                ->call('activarEdicion')
                ->set('form.efectividad', $valor)
                ->set('mensaje', 'Fuera de rango')
                ->call('guardar')
                ->assertHasErrors('form.efectividad');
        }

        $this->assertEquals(100, $control->fresh()->efectividad);
    }
}
