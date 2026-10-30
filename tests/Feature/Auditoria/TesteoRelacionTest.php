<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Control\Show\EvidenciaControl;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Testeo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testeo de la evidencia de cumplimiento de una relación control-riesgo (D-040):
 * sólo el auditor, con el control aprobado y el riesgo validado o aprobado. Se
 * aplica en el acto, sin validación ni comité, y afecta sólo a esa relación.
 */
class TesteoRelacionTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private Area $otraGerencia;

    private User $auditor;

    private Control $control;

    private Riesgo $riesgo;

    private Riesgo $otroRiesgo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->otraGerencia = Area::create(['nombre' => 'Gerencia Auditoría', 'tipo' => TipoArea::Gerencia]);
        // Auditor de otra área: testea igual, el alcance no depende del área.
        $this->auditor = User::factory()->create(['rol' => 'auditor', 'area_id' => $this->otraGerencia->id]);

        $this->control = Control::factory()->create([
            'nombre' => 'Control de stock',
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
            'mitigacion_default' => 6,
        ]);
        // Total 16 en los dos.
        $valores = ['impacto' => 8, 'probabilidad' => 8, 'area_id' => $this->gerencia->id];
        $this->riesgo = Riesgo::factory()->aprobado()->create($valores);
        $this->otroRiesgo = Riesgo::factory()->validado()->create($valores);
        $this->riesgo->controles()->attach($this->control->id, ['mitigacion' => 6]);
        $this->otroRiesgo->controles()->attach($this->control->id, ['mitigacion' => 6]);
    }

    private function efectividadEn(Riesgo $riesgo): int
    {
        return $riesgo->controles()->whereKey($this->control->id)->first()->pivot->efectividad;
    }

    private function residual(Riesgo $riesgo): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->findOrFail($riesgo->id)->valor_residual;
    }

    private function testear(User $usuario, Riesgo $riesgo, int $efectividad = 50, ?string $fecha = null)
    {
        return Livewire::actingAs($usuario)
            ->test(EvidenciaControl::class, ['control' => $this->control])
            ->call('abrirTesteo', $riesgo->id)
            ->set('efectividad', $efectividad)
            ->set('fecha', $fecha ?? now()->subDays(3)->toDateString())
            ->set('comentario', 'Muestra de 20 operaciones, 10 sin evidencia.')
            ->call('guardar');
    }

    #[Test]
    public function un_auditor_registra_un_testeo_y_se_aplica_en_el_acto(): void
    {
        $this->testear($this->auditor, $this->riesgo)
            ->assertHasNoErrors()
            ->assertDispatched('control-actualizado');

        $this->assertSame(50, $this->efectividadEn($this->riesgo));
        $this->assertSame(13, $this->residual($this->riesgo)); // 16 - 3

        $testeo = Testeo::sole();
        $this->assertSame([100, 50], [$testeo->efectividad_antes, $testeo->efectividad_despues]);
        $this->assertSame(now()->subDays(3)->toDateString(), $testeo->fecha->toDateString());
        $this->assertSame($this->auditor->id, $testeo->user_id);

        // Nace aprobado: el historial del riesgo lo cuenta y no queda nada pendiente.
        $entrada = $this->riesgo->actualizaciones()->where('data->origen', 'testeo')->sole();
        $this->assertEquals(Estado::aprobado()->id, $entrada->estado_id);
        $this->assertSame($this->auditor->id, $entrada->aprobado_por_id);
        $this->assertCount(0, $this->riesgo->actualizaciones()->propuestasPendientes()->get());
    }

    #[Test]
    public function un_testeo_en_una_relacion_no_toca_las_otras_del_mismo_control(): void
    {
        $this->testear($this->auditor, $this->riesgo, 0);

        $this->assertSame(0, $this->efectividadEn($this->riesgo));
        $this->assertSame(100, $this->efectividadEn($this->otroRiesgo));
        $this->assertSame(10, $this->residual($this->otroRiesgo));
    }

    #[Test]
    public function un_testeo_que_confirma_el_porcentaje_no_deja_cambio_en_el_riesgo(): void
    {
        $this->testear($this->auditor, $this->riesgo, 100)->assertHasNoErrors();

        $this->assertSame(1, Testeo::count());
        $this->assertCount(0, $this->riesgo->actualizaciones()->where('data->origen', 'testeo')->get());
    }

    #[Test]
    public function nadie_mas_que_el_auditor_registra_un_testeo(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        foreach ([$gerente, $empleado, $comite] as $usuario) {
            Livewire::actingAs($usuario)
                ->test(EvidenciaControl::class, ['control' => $this->control])
                ->call('abrirTesteo', $this->riesgo->id)
                ->assertForbidden();

            Livewire::actingAs($usuario)
                ->test(EvidenciaControl::class, ['control' => $this->control])
                ->set('riesgoId', $this->riesgo->id)
                ->set('efectividad', 10)
                ->set('fecha', now()->toDateString())
                ->set('comentario', 'Intento')
                ->call('guardar')
                ->assertForbidden();
        }

        $this->assertSame(100, $this->efectividadEn($this->riesgo));
        $this->assertSame(0, Testeo::count());
    }

    #[Test]
    public function el_auditor_no_testea_si_el_control_no_esta_aprobado(): void
    {
        $this->control->update(['estado_id' => Estado::validado()->id]);

        Livewire::actingAs($this->auditor)
            ->test(EvidenciaControl::class, ['control' => $this->control])
            ->assertViewHas('puedeTestear', fn ($p) => $p[$this->riesgo->id] === false)
            ->call('abrirTesteo', $this->riesgo->id)
            ->assertForbidden();
    }

    #[Test]
    public function el_auditor_no_testea_una_relacion_con_un_riesgo_en_borrador(): void
    {
        $borrador = Riesgo::factory()->create(['area_id' => $this->gerencia->id]);
        $borrador->controles()->attach($this->control->id, ['mitigacion' => 6]);

        $this->assertFalse($this->auditor->can('testear', [$this->control, $borrador]));
        $this->assertTrue($this->auditor->can('testear', [$this->control, $this->otroRiesgo]));
    }

    #[Test]
    public function la_fecha_del_testeo_no_puede_ser_futura(): void
    {
        $this->testear($this->auditor, $this->riesgo, 50, now()->addDay()->toDateString())
            ->assertHasErrors(['fecha']);

        $this->assertSame(100, $this->efectividadEn($this->riesgo));
    }

    #[Test]
    public function el_comentario_es_obligatorio_y_el_porcentaje_va_de_0_a_100(): void
    {
        Livewire::actingAs($this->auditor)
            ->test(EvidenciaControl::class, ['control' => $this->control])
            ->call('abrirTesteo', $this->riesgo->id)
            ->set('efectividad', 120)
            ->set('comentario', '')
            ->call('guardar')
            ->assertHasErrors(['efectividad', 'comentario']);

        $this->assertSame(0, Testeo::count());
    }

    #[Test]
    public function un_testeo_guarda_sus_adjuntos(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->auditor)
            ->test(EvidenciaControl::class, ['control' => $this->control])
            ->call('abrirTesteo', $this->riesgo->id)
            ->set('efectividad', 80)
            ->set('fecha', now()->toDateString())
            ->set('comentario', 'Informe adjunto')
            ->set('archivos', [UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf')])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('informe.pdf', Testeo::sole()->getFirstMedia('adjuntos')?->file_name);
    }
}
