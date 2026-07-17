<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Database\Seeders\TipoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un TipoRiesgo con `restringe_respuesta` (Corrupción) no admite compartir ni
 * aceptar como respuesta: el riesgo no se puede transferir a un tercero ni
 * convivir con él. Ver RespuestaRiesgo::restringidas() y
 * RiesgoController::reglaRespuesta().
 */
class RiesgoRespuestaRestringidaTest extends TestCase
{
    use RefreshDatabase;

    private TipoRiesgo $corrupcion;

    private TipoRiesgo $operacional;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->corrupcion = TipoRiesgo::factory()->create([
            'nombre' => 'Corrupción',
            'restringe_respuesta' => true,
        ]);
        $this->operacional = TipoRiesgo::factory()->create([
            'nombre' => 'Operacional',
            'restringe_respuesta' => false,
        ]);
        $this->usuario = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
    }

    private function datosWizard(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Riesgo de prueba',
            'tipo_riesgo_id' => $this->corrupcion->id,
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ], $overrides);
    }

    /** @test */
    public function no_se_puede_crear_un_riesgo_de_corrupcion_con_respuesta_compartir(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard(['respuesta' => 'compartir']));

        $respuesta->assertSessionHasErrors('respuesta');
        $this->assertDatabaseCount('riesgos', 0);
    }

    /** @test */
    public function no_se_puede_crear_un_riesgo_de_corrupcion_con_respuesta_aceptar(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard(['respuesta' => 'aceptar']));

        $respuesta->assertSessionHasErrors('respuesta');
        $this->assertDatabaseCount('riesgos', 0);
    }

    /** @test */
    public function un_riesgo_de_corrupcion_admite_mitigar_y_evitar(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard(['respuesta' => 'evitar']));

        $respuesta->assertSessionHasNoErrors();
        $this->assertEquals(RespuestaRiesgo::Evitar, Riesgo::firstOrFail()->respuesta);
    }

    /** @test */
    public function un_tipo_sin_restriccion_sigue_admitiendo_compartir(): void
    {
        $respuesta = $this->actingAs($this->usuario)->post(route('auditoria.riesgos.store'), $this->datosWizard([
            'tipo_riesgo_id' => $this->operacional->id,
            'respuesta' => 'compartir',
        ]));

        $respuesta->assertSessionHasNoErrors();
        $this->assertEquals(RespuestaRiesgo::Compartir, Riesgo::firstOrFail()->respuesta);
    }

    /** @test */
    public function no_se_puede_pasar_un_riesgo_compartido_a_tipo_corrupcion_al_editarlo(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $this->usuario->id,
            'tipo_riesgo_id' => $this->operacional->id,
            'respuesta' => RespuestaRiesgo::Compartir,
        ]);

        $respuesta = $this->actingAs($this->usuario)->put(route('auditoria.riesgos.update', $riesgo), [
            'nombre' => $riesgo->nombre,
            'tipo_riesgo_id' => $this->corrupcion->id,
            'respuesta' => 'compartir',
        ]);

        $respuesta->assertSessionHasErrors('respuesta');
        $this->assertEquals($this->operacional->id, $riesgo->fresh()->tipo_riesgo_id);
    }

    /** @test */
    public function el_seeder_marca_corrupcion_como_tipo_restringido(): void
    {
        TipoRiesgo::query()->forceDelete();
        $this->seed(TipoRiesgoSeeder::class);

        $this->assertTrue(TipoRiesgo::where('nombre', 'Corrupción')->value('restringe_respuesta'));
        $this->assertFalse(TipoRiesgo::where('nombre', 'Operacional')->value('restringe_respuesta'));
    }
}
