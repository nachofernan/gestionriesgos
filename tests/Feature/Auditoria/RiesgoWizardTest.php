<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function datosWizard(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Riesgo de prueba',
            'descripcion' => 'Descripción de prueba',
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
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
    public function el_historial_de_actualizaciones_se_renderiza_sin_error_tras_crear_el_riesgo(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);

        $this->actingAs($user)->post(route('auditoria.riesgos.store'), $this->datosWizard());
        $riesgo = Riesgo::firstOrFail();

        $respuesta = $this->actingAs($user)->get(route('auditoria.riesgos.show', $riesgo));

        $respuesta->assertStatus(200);
    }

    /** @test */
    public function el_riesgo_creado_sin_area_toma_el_area_del_usuario(): void
    {
        $area = Area::create(['nombre' => 'Área de prueba']);
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => $area->id]);

        $datos = $this->datosWizard();
        unset($datos['area_id']);

        $this->actingAs($user)->post(route('auditoria.riesgos.store'), $datos);

        $this->assertEquals($area->id, Riesgo::firstOrFail()->area_id);
    }

    /** @test */
    public function el_area_del_creador_queda_sincronizada_como_gerencia_del_riesgo(): void
    {
        $area = Area::create(['nombre' => 'Área de prueba']);
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => $area->id]);

        $this->actingAs($user)->post(route('auditoria.riesgos.store'), $this->datosWizard());

        $riesgo = Riesgo::firstOrFail();
        $this->assertCount(1, $riesgo->areas);
        $this->assertEquals($area->id, $riesgo->areas->first()->id);
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
        $riesgo = Riesgo::factory()->borrador()->create(['user_id' => $gerente->id]);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $respuesta->assertSessionHas('error');
        $this->assertEquals('borrador', $riesgo->fresh()->estado->nombre);
    }

    /** @test */
    public function no_se_puede_validar_un_riesgo_con_respuesta_mitigar_sin_plan_de_accion(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo de prueba']);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $gerente->id,
            'respuesta' => RespuestaRiesgo::Mitigar,
        ]);
        $riesgo->objetivos()->attach($objetivo);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $respuesta->assertSessionHas('error');
        $this->assertEquals('borrador', $riesgo->fresh()->estado->nombre);
    }

    /**
     * Un plan que sólo existe (en borrador) ya no alcanza: desde que el plan es
     * un prerequisito bloqueante (ver PlanRequeridoParaMitigarTest), tiene que
     * estar validado para poder validar el riesgo.
     */
    /** @test */
    public function se_puede_validar_un_riesgo_con_respuesta_mitigar_si_tiene_objetivo_y_plan_validado(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo de prueba', 'estado_id' => Estado::validado()->id]);
        $plan = PlanAccion::factory()->create(['estado_id' => Estado::validado()->id]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $gerente->id,
            'respuesta' => RespuestaRiesgo::Mitigar,
        ]);
        $riesgo->objetivos()->attach($objetivo);
        $riesgo->planesAccion()->attach($plan);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $respuesta->assertSessionHasNoErrors();
        $this->assertEquals('validado', $riesgo->fresh()->estado->nombre);
    }

    /** @test */
    public function un_plan_en_borrador_no_alcanza_para_validar_un_riesgo_mitigar_aunque_exista(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo de prueba']);
        $plan = PlanAccion::factory()->create(); // borrador por defecto
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $gerente->id,
            'respuesta' => RespuestaRiesgo::Mitigar,
        ]);
        $riesgo->objetivos()->attach($objetivo);
        $riesgo->planesAccion()->attach($plan);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $respuesta->assertSessionHas('error');
        $this->assertEquals('borrador', $riesgo->fresh()->estado->nombre);
    }

    /** @test */
    public function se_puede_validar_un_riesgo_sin_mitigar_con_solo_un_objetivo(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo de prueba', 'estado_id' => Estado::validado()->id]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $gerente->id,
            'respuesta' => RespuestaRiesgo::Aceptar,
        ]);
        $riesgo->objetivos()->attach($objetivo);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $this->assertEquals('validado', $riesgo->fresh()->estado->nombre);
    }

    /** @test */
    public function la_clasificacion_traduce_el_valor_a_su_etiqueta_cualitativa(): void
    {
        $this->assertEquals('bajo', Riesgo::clasificacion(0)['etiqueta']);
        $this->assertEquals('bajo', Riesgo::clasificacion(9)['etiqueta']);
        $this->assertEquals('moderado', Riesgo::clasificacion(10)['etiqueta']);
        $this->assertEquals('moderado', Riesgo::clasificacion(13)['etiqueta']);
        $this->assertEquals('critico', Riesgo::clasificacion(14)['etiqueta']);
        $this->assertEquals('critico', Riesgo::clasificacion(20)['etiqueta']);
    }
}
