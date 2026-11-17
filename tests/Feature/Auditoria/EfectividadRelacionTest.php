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
 * Evidencia de cumplimiento de cada relación control-riesgo (columna
 * `efectividad`, D-051): regula por tramos cuánto del valor del control
 * descuenta del residual. < 60 → 0 · 60–69 → 20% · 70–79 → 40% · 80–90 → 60% ·
 * 91–98 → 80% · 99–100 → 100%. Redondea hacia abajo y por relación (D-037).
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

    private function control(int $valor = 10, string $estado = 'aprobado'): Control
    {
        return Control::factory()->create([
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
            'mitigacion_default' => $valor,
        ]);
    }

    /** Riesgo de total 16. */
    private function riesgo(): Riesgo
    {
        return Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8, 'area_id' => $this->gerencia->id]);
    }

    private function riesgoConControl(int $evidencia, int $valor = 10, string $estado = 'aprobado'): Riesgo
    {
        $riesgo = $this->riesgo();
        $riesgo->controles()->attach($this->control($valor, $estado)->id, ['efectividad' => $evidencia]);

        return $riesgo;
    }

    private function mitigacion(Riesgo $riesgo): int
    {
        return Riesgo::with('controles.estado')->findOrFail($riesgo->id)->mitigacion_controles;
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
        $riesgo->controles()->attach($this->control()->id);

        $this->assertSame(100, $riesgo->controles()->first()->pivot->efectividad);
        $this->assertSame(10, $this->mitigacion($riesgo));
    }

    #[Test]
    public function una_evidencia_menor_a_60_no_mitiga_nada(): void
    {
        $this->assertSame(0, $this->mitigacion($this->riesgoConControl(59)));
        $this->assertSame(0, $this->mitigacion($this->riesgoConControl(0)));
        $this->assertEquals(16, $this->residual($this->riesgoConControl(30)));
    }

    #[Test]
    public function cada_tramo_de_evidencia_mitiga_su_porcentaje_hacia_abajo(): void
    {
        // Control de 10: cada tramo, en sus dos bordes.
        $esperado = [60 => 2, 69 => 2, 70 => 4, 79 => 4, 80 => 6, 90 => 6, 91 => 8, 98 => 8, 99 => 10, 100 => 10];
        foreach ($esperado as $evidencia => $mitiga) {
            $this->assertSame($mitiga, $this->mitigacion($this->riesgoConControl($evidencia)), "evidencia {$evidencia}");
        }

        // Control de 7 al 60%: 1,4 → 1.
        $this->assertSame(1, $this->mitigacion($this->riesgoConControl(60, valor: 7)));
    }

    #[Test]
    public function una_evidencia_de_98_no_mitiga_el_valor_entero(): void
    {
        $this->assertEquals(8, $this->residual($this->riesgoConControl(98))); // 16 - 8
        $this->assertEquals(6, $this->residual($this->riesgoConControl(99))); // 16 - 10
    }

    #[Test]
    public function la_mitigacion_efectiva_se_redondea_por_relacion(): void
    {
        $riesgo = $this->riesgoConControl(70, valor: 7); // 2,8 → 2
        $riesgo->controles()->attach($this->control(7)->id, ['efectividad' => 70]);

        // 2 + 2 = 4, no floor(2,8 + 2,8) = 5.
        $this->assertEquals(12, $this->residual($riesgo));
    }

    #[Test]
    public function dos_relaciones_del_mismo_control_mitigan_segun_su_propia_evidencia(): void
    {
        $control = $this->control(6);
        $uno = $this->riesgo();
        $otro = $this->riesgo();
        $uno->controles()->attach($control->id, ['efectividad' => 100]);
        $otro->controles()->attach($control->id, ['efectividad' => 80]);

        $this->assertEquals(10, $this->residual($uno)); // 16 - 6
        $this->assertEquals(13, $this->residual($otro)); // 16 - 3 (6 × 60% = 3,6 → 3)
    }

    #[Test]
    public function un_control_en_borrador_no_mitiga_aunque_la_relacion_este_al_100(): void
    {
        $this->assertEquals(16, $this->residual($this->riesgoConControl(100, estado: 'borrador')));
    }

    #[Test]
    public function la_preview_de_gestion_controles_cuadra_con_el_residual_del_modelo(): void
    {
        $riesgo = $this->riesgoConControl(80, valor: 4); // 2,4 → 2: residual 14
        $nuevo = $this->control(5);

        $this->assertEquals(14, $this->residual($riesgo));

        Livewire::actingAs(User::factory()->create(['rol' => 'comite', 'area_id' => null]))
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $nuevo->id)
            ->assertDispatched('residual-actualizado', valor: 9); // 16 - 2 - 5 (el nuevo nace al 100%)
    }

    #[Test]
    public function quitar_y_volver_a_agregar_un_control_conserva_su_efectividad(): void
    {
        $riesgo = $this->riesgoConControl(80, valor: 4);
        $controlId = $riesgo->controles()->first()->id;

        Livewire::actingAs(User::factory()->create(['rol' => 'comite', 'area_id' => null]))
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $controlId)
            ->call('agregar', $controlId)
            ->assertDispatched('residual-actualizado', valor: 14)
            ->call('guardar');

        $this->assertSame(80, $riesgo->controles()->first()->pivot->efectividad);
        $this->assertEquals(14, $this->residual($riesgo));
    }
}
