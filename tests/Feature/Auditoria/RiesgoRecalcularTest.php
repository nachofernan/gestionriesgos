<?php

namespace Tests\Feature\Auditoria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;

/**
 * Cubre que impacto/probabilidad de un riesgo dejaron de editarse a mano en
 * update() y sólo cambian a través de recalcular()/recalcularStore(), que
 * repite el wizard de preguntas (ver RiesgoController y
 * config/riesgo_preguntas.php).
 */
class RiesgoRecalcularTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EstadoRiesgoSeeder::class);
    }

    /** @test */
    public function editar_un_riesgo_no_modifica_impacto_ni_probabilidad_aunque_se_envien(): void
    {
        $user   = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id'  => $user->id,
            'impacto'  => 5,
            'probabilidad' => 4,
        ]);

        $this->actingAs($user)->put(route('auditoria.riesgos.update', $riesgo), [
            'nombre'         => 'Nombre actualizado',
            'impacto'        => 10,
            'probabilidad'   => 10,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);

        $riesgo->refresh();
        $this->assertEquals('Nombre actualizado', $riesgo->nombre);
        $this->assertEquals(5, $riesgo->impacto);
        $this->assertEquals(4, $riesgo->probabilidad);
    }

    /** @test */
    public function la_pagina_de_recalcular_renderiza_el_wizard_de_preguntas(): void
    {
        $user   = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create(['user_id' => $user->id]);

        $respuesta = $this->actingAs($user)->get(route('auditoria.riesgos.recalcular', $riesgo));

        $respuesta->assertStatus(200);
        $respuesta->assertSee('¿Este riesgo ya ocurrió en el pasado en esta área o similares?');
    }

    /** @test */
    public function recalcular_actualiza_impacto_y_probabilidad_segun_las_respuestas(): void
    {
        $user   = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $user->id,
            'impacto' => 0,
            'probabilidad' => 0,
        ]);

        $this->actingAs($user)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas'      => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ]);

        $riesgo->refresh();
        $this->assertEquals(6, $riesgo->probabilidad);
        $this->assertEquals(3, $riesgo->impacto);
    }

    /** @test */
    public function recalcular_deja_registro_del_cambio_en_el_historial(): void
    {
        $user   = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $user->id,
            'impacto' => 0,
            'probabilidad' => 0,
        ]);

        $this->actingAs($user)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas'      => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ]);

        $actualizacion = $riesgo->actualizaciones()->first();
        $this->assertEquals('edicion', $actualizacion->data['tipo']);
        $this->assertEquals(0, $actualizacion->data['diff']['campos']['impacto']['antes']);
        $this->assertEquals(3, $actualizacion->data['diff']['campos']['impacto']['despues']);
    }

    /** @test */
    public function no_se_puede_recalcular_un_riesgo_ya_validado(): void
    {
        $user   = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->validado()->create(['user_id' => $user->id]);

        $respuesta = $this->actingAs($user)->get(route('auditoria.riesgos.recalcular', $riesgo));

        $respuesta->assertRedirect(route('auditoria.riesgos.show', $riesgo));
    }
}
