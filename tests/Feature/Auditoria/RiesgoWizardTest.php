<?php

namespace Tests\Feature\Auditoria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;

/**
 * Cubre el wizard de creación de riesgo (impacto/probabilidad calculados a
 * partir de las respuestas de config/riesgo_preguntas.php, objetivos opcional)
 * y los nuevos prerequisitos duros para validar (objetivo obligatorio, y plan
 * de acción obligatorio si la respuesta es mitigar). Ver Riesgo::motivosBloqueoValidacion().
 */
class RiesgoWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EstadoRiesgoSeeder::class);
    }

    private function datosWizard(array $overrides = []): array
    {
        return array_merge([
            'nombre'        => 'Riesgo de prueba',
            'descripcion'   => 'Descripción de prueba',
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas'      => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ], $overrides);
    }

    /** @test */
    public function la_pagina_de_creacion_renderiza_el_wizard(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        TipoRiesgo::factory()->create();

        $respuesta = $this->actingAs($user)->get(route('auditoria.riesgos.create'));

        $respuesta->assertStatus(200);
        $respuesta->assertSee('¿Este riesgo ya ocurrió en el pasado en esta área o similares?');
        $respuesta->assertSee('¿Podría generar pérdidas económicas, sanciones o multas?');
    }

    /** @test */
    public function el_wizard_crea_el_riesgo_con_impacto_y_probabilidad_calculados_de_las_respuestas(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);

        $respuesta = $this->actingAs($user)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard());

        $riesgo = Riesgo::firstOrFail();
        $respuesta->assertRedirect(route('auditoria.riesgos.show', $riesgo));
        $this->assertEquals(6, $riesgo->probabilidad); // 2+1+0+1+2
        $this->assertEquals(3, $riesgo->impacto);      // 1+1+0+0+1
    }

    /** @test */
    public function el_wizard_no_exige_objetivos_para_crear_el_riesgo(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);

        $respuesta = $this->actingAs($user)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard());

        $respuesta->assertSessionDoesntHaveErrors('objetivos');
        $this->assertCount(0, Riesgo::firstOrFail()->objetivos);
    }

    /** @test */
    public function el_wizard_exige_las_cinco_respuestas_de_cada_dimension(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);

        $respuesta = $this->actingAs($user)->post(route('auditoria.riesgos.store'), $this->datosWizard([
            'probabilidad_respuestas' => [1 => 2, 2 => 1],
        ]));

        $respuesta->assertSessionHasErrors('probabilidad_respuestas');
        $this->assertDatabaseCount('riesgos', 0);
    }

    /** @test */
    public function no_se_puede_validar_un_riesgo_sin_objetivos(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo  = Riesgo::factory()->borrador()->create(['user_id' => $gerente->id]);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $respuesta->assertSessionHas('error');
        $this->assertEquals('borrador', $riesgo->fresh()->estado->nombre);
    }

    /** @test */
    public function no_se_puede_validar_un_riesgo_con_respuesta_mitigar_sin_plan_de_accion(): void
    {
        $gerente  = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo de prueba']);
        $riesgo   = Riesgo::factory()->borrador()->create([
            'user_id'   => $gerente->id,
            'respuesta' => \App\Enums\Auditoria\RespuestaRiesgo::Mitigar,
        ]);
        $riesgo->objetivos()->attach($objetivo);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $respuesta->assertSessionHas('error');
        $this->assertEquals('borrador', $riesgo->fresh()->estado->nombre);
    }

    /** @test */
    public function se_puede_validar_un_riesgo_con_respuesta_mitigar_si_tiene_objetivo_y_plan_de_accion(): void
    {
        $gerente  = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo de prueba']);
        $plan     = PlanAccion::factory()->create();
        $riesgo   = Riesgo::factory()->borrador()->create([
            'user_id'   => $gerente->id,
            'respuesta' => \App\Enums\Auditoria\RespuestaRiesgo::Mitigar,
        ]);
        $riesgo->objetivos()->attach($objetivo);
        $riesgo->planesAccion()->attach($plan);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $respuesta->assertSessionHasNoErrors();
        $this->assertEquals('validado', $riesgo->fresh()->estado->nombre);
    }

    /** @test */
    public function se_puede_validar_un_riesgo_sin_mitigar_con_solo_un_objetivo(): void
    {
        $gerente  = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo de prueba']);
        $riesgo   = Riesgo::factory()->borrador()->create([
            'user_id'   => $gerente->id,
            'respuesta' => \App\Enums\Auditoria\RespuestaRiesgo::Aceptar,
        ]);
        $riesgo->objetivos()->attach($objetivo);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $this->assertEquals('validado', $riesgo->fresh()->estado->nombre);
    }
}
