<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `fundamento` es texto libre, obligatorio sólo cuando la respuesta al riesgo no
 * lo reduce por sí misma (compartir, aceptar, evitar) y por lo tanto hay que
 * justificarla. Ver RespuestaRiesgo::exigenFundamento().
 */
class RiesgoFundamentoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
        $this->usuario = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
    }

    private function datosWizard(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Riesgo de prueba',
            'tipo_riesgo_id' => TipoRiesgo::factory()->create(['restringe_respuesta' => false])->id,
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ], $overrides);
    }

    public static function respuestasQueExigenFundamento(): array
    {
        return [
            'compartir' => ['compartir'],
            'aceptar' => ['aceptar'],
            'evitar' => ['evitar'],
        ];
    }

    #[Test]
    #[DataProvider('respuestasQueExigenFundamento')]
    public function no_se_puede_crear_un_riesgo_sin_fundamento_si_la_respuesta_lo_exige(string $respuestaRiesgo): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard(['respuesta' => $respuestaRiesgo]));

        $respuesta->assertSessionHasErrors('fundamento');
        $this->assertDatabaseCount('riesgos', 0);
    }

    #[Test]
    #[DataProvider('respuestasQueExigenFundamento')]
    public function se_crea_el_riesgo_con_fundamento_si_la_respuesta_lo_exige(string $respuestaRiesgo): void
    {
        $respuesta = $this->actingAs($this->usuario)->post(route('auditoria.riesgos.store'), $this->datosWizard([
            'respuesta' => $respuestaRiesgo,
            'fundamento' => 'El costo de mitigarlo supera al impacto esperado.',
        ]));

        $respuesta->assertSessionHasNoErrors();
        $this->assertEquals('El costo de mitigarlo supera al impacto esperado.', Riesgo::firstOrFail()->fundamento);
    }

    #[Test]
    public function mitigar_no_exige_fundamento(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard(['respuesta' => 'mitigar']));

        $respuesta->assertSessionHasNoErrors();
        $this->assertNull(Riesgo::firstOrFail()->fundamento);
    }

    #[Test]
    public function un_riesgo_sin_respuesta_no_exige_fundamento(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard());

        $respuesta->assertSessionHasNoErrors();
        $this->assertNull(Riesgo::firstOrFail()->fundamento);
    }

    #[Test]
    public function no_se_puede_editar_un_riesgo_dejando_sin_fundamento_una_respuesta_que_lo_exige(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $this->usuario->id,
            'respuesta' => RespuestaRiesgo::Mitigar,
        ]);

        $respuesta = $this->actingAs($this->usuario)->put(route('auditoria.riesgos.update', $riesgo), [
            'nombre' => $riesgo->nombre,
            'tipo_riesgo_id' => $riesgo->tipo_riesgo_id,
            'respuesta' => 'aceptar',
        ]);

        $respuesta->assertSessionHasErrors('fundamento');
        $this->assertEquals(RespuestaRiesgo::Mitigar, $riesgo->fresh()->respuesta);
    }

    #[Test]
    public function el_fundamento_queda_registrado_en_el_historial_al_editarlo(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $this->usuario->id,
            'respuesta' => RespuestaRiesgo::Aceptar,
            'fundamento' => 'Fundamento viejo',
        ]);

        $this->actingAs($this->usuario)->put(route('auditoria.riesgos.update', $riesgo), [
            'nombre' => $riesgo->nombre,
            'tipo_riesgo_id' => $riesgo->tipo_riesgo_id,
            'respuesta' => 'aceptar',
            'fundamento' => 'Fundamento nuevo',
        ]);

        $this->assertEquals('Fundamento nuevo', $riesgo->fresh()->fundamento);
        $diff = $riesgo->actualizaciones()->latest('id')->first()->data['diff']['campos'];
        $this->assertEquals('Fundamento viejo', $diff['fundamento']['antes']);
        $this->assertEquals('Fundamento nuevo', $diff['fundamento']['despues']);
    }
}
