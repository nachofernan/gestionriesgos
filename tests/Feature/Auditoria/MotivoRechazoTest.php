<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\AccionActualizacionModal;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Todo rechazo lleva motivo (D-031): el de un elemento queda en su historial como
 * rechazo, y el de una propuesta en `motivo_rechazo`. Sin motivo no se rechaza nada.
 */
class MotivoRechazoTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private User $empleado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia RRHH', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
    }

    private function propuesta(): Actualizacion
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->gerencia->id])->load('estado');

        return Actualizacion::registrarCambioCampos($riesgo, $this->empleado, 'Cambio de nombre', ['nombre' => 'Otro nombre']);
    }

    #[Test]
    public function rechazar_un_elemento_sin_motivo_no_cambia_nada(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id]);

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.rechazar', $riesgo))
            ->assertSessionHasErrors('motivo');

        $this->assertSame('borrador', $riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function el_rechazo_de_un_elemento_queda_en_su_historial_como_rechazo(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id]);

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.rechazar', $riesgo), ['motivo' => 'Está duplicado'])
            ->assertSessionHasNoErrors();

        $registro = $riesgo->actualizaciones()->sole();
        $this->assertSame('borrado', $registro->estado->nombre);
        $this->assertEquals($this->gerente->id, $registro->rechazado_por_id);
        $this->assertSame('Está duplicado', $registro->motivo_rechazo);
        $this->assertStringContainsString('Está duplicado', $registro->mensaje);
    }

    #[Test]
    public function rechazar_una_propuesta_exige_motivo_y_lo_guarda(): void
    {
        $propuesta = $this->propuesta();
        $componente = Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $propuesta->actualizable_id]);

        $componente->call('rechazarActualizacion', $propuesta->id)->assertHasErrors('motivo');
        $this->assertSame('borrador', $propuesta->fresh()->estado->nombre);

        $componente->call('resolver', $propuesta->id, 'rechazar', 'No hace falta cambiarlo');
        $propuesta->refresh();
        $this->assertSame('borrado', $propuesta->estado->nombre);
        $this->assertSame('No hace falta cambiarlo', $propuesta->motivo_rechazo);
    }

    #[Test]
    public function el_modal_de_pendientes_exige_motivo_para_rechazar(): void
    {
        $propuesta = $this->propuesta();

        Livewire::actingAs($this->gerente)
            ->test(AccionActualizacionModal::class)
            ->call('abrir', $propuesta->id, 'rechazar')
            ->call('confirmar')
            ->assertHasErrors('motivo')
            ->set('motivo', 'Ya se resolvió por otro lado')
            ->call('confirmar')
            ->assertHasNoErrors();

        $this->assertSame('Ya se resolvió por otro lado', $propuesta->fresh()->motivo_rechazo);
    }
}
