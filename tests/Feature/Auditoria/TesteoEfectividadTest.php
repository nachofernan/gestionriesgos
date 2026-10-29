<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Control\Show\EfectividadControl;
use App\Livewire\Auditoria\Control\Show\FichaControl;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testeos de efectividad del control (D-038): la única vía para cambiar la
 * efectividad. Fuera de borrador es una propuesta con el circuito de siempre; sin
 * cambio de valor queda como testeo informativo; en borrador se aplica directo.
 */
class TesteoEfectividadTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private Area $otraGerencia;

    private User $auditor;

    private User $gerente;

    private User $comite;

    private Control $control;

    private Riesgo $riesgo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
        Storage::fake('public');

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->otraGerencia = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia]);
        $auditoria = Area::create(['nombre' => 'Auditoría Interna', 'tipo' => TipoArea::Gerencia]);

        $this->auditor = User::factory()->create(['rol' => 'auditor', 'area_id' => $auditoria->id]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        // Riesgo de total 16 con un control aprobado que mitiga 4 al 100%: residual 12.
        $this->riesgo = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id, 'impacto' => 8, 'probabilidad' => 8]);
        $this->control = Control::factory()->create([
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
            'mitigacion_default' => 4,
        ]);
        $this->riesgo->controles()->attach($this->control->id, ['mitigacion' => 4]);
    }

    private function residual(): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->findOrFail($this->riesgo->id)->valor_residual;
    }

    private function testear(User $usuario, int $efectividad, string $motivo = 'Testeo semestral', array $archivos = [])
    {
        return Livewire::actingAs($usuario)
            ->test(EfectividadControl::class, ['control' => $this->control])
            ->call('abrirModal')
            ->set('efectividad', $efectividad)
            ->set('motivo', $motivo)
            ->set('archivos', $archivos)
            ->call('guardar');
    }

    #[Test]
    public function un_auditor_registra_un_testeo_y_queda_propuesta_hasta_validarse(): void
    {
        $this->testear($this->auditor, 50)->assertHasNoErrors()->assertDispatched('control-actualizado');

        $propuesta = $this->control->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertEquals('testeo', $propuesta->data['origen']);
        $this->assertEquals(Estado::borrador()->id, $propuesta->estado_id);
        $this->assertEquals(100, $this->control->fresh()->efectividad);
        $this->assertEquals(12, $this->residual());

        $propuesta->marcarValidada($this->gerente);
        $this->assertEquals(12, $this->residual()); // control aprobado: espera al comité

        $propuesta->fresh()->marcarAprobada($this->comite);
        $this->assertEquals(50, $this->control->fresh()->efectividad);
        $this->assertEquals(14, $this->residual()); // 16 - 4 × 50%
    }

    #[Test]
    public function el_comite_registra_un_testeo_sobre_un_control_aprobado_y_se_aplica_en_el_acto(): void
    {
        $this->testear($this->comite, 25)->assertHasNoErrors();

        $this->assertEquals(25, $this->control->fresh()->efectividad);
        $this->assertEquals(15, $this->residual()); // 16 - 1
        $this->assertCount(0, $this->control->actualizaciones()->propuestasPendientes()->get());
    }

    #[Test]
    public function un_testeo_sin_cambio_queda_como_informativo_y_no_es_propuesta(): void
    {
        $this->testear($this->auditor, 100, 'Confirma que funciona')->assertHasNoErrors();

        $testeo = $this->control->actualizaciones()->where('data->origen', 'testeo')->sole();
        $this->assertNull($testeo->estado_id);
        $this->assertEquals('testeo', $testeo->data['tipo']);
        $this->assertEquals(100, $testeo->data['efectividad']);
        $this->assertCount(0, $this->control->actualizaciones()->propuestasPendientes()->get());
        $this->assertEquals(0, Actualizacion::where('data->tipo', 'cambio')->count()); // fuera de Pendientes
    }

    #[Test]
    public function un_testeo_guarda_sus_adjuntos(): void
    {
        $this->testear($this->auditor, 60, 'Informe Q3', [UploadedFile::fake()->create('informe.pdf', 50, 'application/pdf')])
            ->assertHasNoErrors();

        $testeo = $this->control->actualizaciones()->where('data->origen', 'testeo')->sole();
        $this->assertEquals(['informe.pdf'], $testeo->getMedia('adjuntos')->pluck('file_name')->all());
    }

    #[Test]
    public function en_un_control_en_borrador_el_testeo_se_aplica_directo_para_quien_lo_gestiona(): void
    {
        $this->control->update(['estado_id' => Estado::borrador()->id]);

        $this->testear($this->gerente, 70)->assertHasNoErrors();

        $this->assertEquals(70, $this->control->fresh()->efectividad);
        $registro = $this->control->actualizaciones()->where('data->origen', 'testeo')->sole();
        $this->assertEquals('edicion', $registro->data['tipo']);
        $this->assertEquals(['antes' => 100, 'despues' => 70], $registro->data['diff']['campos']['efectividad']);
    }

    #[Test]
    public function un_empleado_ajeno_no_puede_registrar_un_testeo(): void
    {
        $ajeno = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->otraGerencia->id]);

        Livewire::actingAs($ajeno)
            ->test(EfectividadControl::class, ['control' => $this->control])
            ->call('abrirModal')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(EfectividadControl::class, ['control' => $this->control])
            ->set('efectividad', 10)
            ->set('motivo', 'Intento ajeno')
            ->call('guardar')
            ->assertForbidden();

        $this->assertEquals(100, $this->control->fresh()->efectividad);
        $this->assertEquals(0, $this->control->actualizaciones()->count());
    }

    #[Test]
    public function un_auditor_no_testea_un_borrador_de_otra_gerencia(): void
    {
        $this->control->update(['estado_id' => Estado::borrador()->id]);

        Livewire::actingAs($this->auditor)
            ->test(EfectividadControl::class, ['control' => $this->control])
            ->set('efectividad', 10)
            ->set('motivo', 'Testeo')
            ->call('guardar')
            ->assertForbidden();

        $this->assertEquals(100, $this->control->fresh()->efectividad);
    }

    #[Test]
    public function no_se_registra_un_segundo_testeo_con_cambio_mientras_hay_uno_pendiente(): void
    {
        $this->testear($this->auditor, 50)->assertHasNoErrors();

        $this->testear($this->auditor, 30)->assertHasErrors('efectividad');

        $this->assertCount(1, $this->control->actualizaciones()->propuestasPendientes()->get());

        // Una confirmación sin cambio sí se puede registrar.
        $this->testear($this->auditor, 100, 'Re-testeo')->assertHasNoErrors();
    }

    #[Test]
    public function la_efectividad_fuera_de_rango_se_rechaza(): void
    {
        foreach ([101, -1] as $valor) {
            $this->testear($this->comite, $valor)->assertHasErrors('efectividad');
        }

        $this->assertEquals(100, $this->control->fresh()->efectividad);
    }

    #[Test]
    public function el_motivo_es_obligatorio(): void
    {
        $this->testear($this->comite, 50, '')->assertHasErrors('motivo');

        $this->assertEquals(100, $this->control->fresh()->efectividad);
    }

    #[Test]
    public function la_ficha_ya_no_edita_la_efectividad(): void
    {
        Livewire::actingAs($this->comite)
            ->test(FichaControl::class, ['control' => $this->control])
            ->call('activarEdicion')
            ->set('form.efectividad', 10)
            ->set('form.nombre', 'Nombre nuevo')
            ->set('mensaje', 'Cambio de nombre')
            ->call('guardar')
            ->assertHasNoErrors();

        $control = $this->control->fresh();
        $this->assertEquals('Nombre nuevo', $control->nombre);
        $this->assertEquals(100, $control->efectividad);
    }
}
