<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre que impacto/probabilidad de un riesgo dejaron de editarse a mano en
 * update() y sólo cambian a través de recalcular()/recalcularStore(), que
 * repite el wizard de preguntas (ver RiesgoController y
 * config/riesgo_preguntas.php). El wizard está disponible en cualquier estado
 * salvo aprobado, y nunca para el comité; fuera de borrador es una propuesta
 * de cambio más (arranca según el rol de quien la crea, no se aplica sola).
 */
class RiesgoRecalcularTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    #[Test]
    public function editar_un_riesgo_no_modifica_impacto_ni_probabilidad_aunque_se_envien(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $user->id,
            'impacto' => 5,
            'probabilidad' => 4,
        ]);

        $this->actingAs($user)->put(route('auditoria.riesgos.update', $riesgo), [
            'nombre' => 'Nombre actualizado',
            'impacto' => 10,
            'probabilidad' => 10,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
            'respuesta' => 'mitigar',
        ]);

        $riesgo->refresh();
        $this->assertEquals('Nombre actualizado', $riesgo->nombre);
        $this->assertEquals(5, $riesgo->impacto);
        $this->assertEquals(4, $riesgo->probabilidad);
    }

    #[Test]
    public function la_pagina_de_recalcular_renderiza_el_wizard_de_preguntas(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create(['user_id' => $user->id]);

        $respuesta = $this->actingAs($user)->get(route('auditoria.riesgos.recalcular', $riesgo));

        $respuesta->assertStatus(200);
        $respuesta->assertSee('¿Este riesgo ya ocurrió en el pasado en esta área o similares?');
    }

    #[Test]
    public function recalcular_actualiza_impacto_y_probabilidad_segun_las_respuestas(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $user->id,
            'impacto' => 0,
            'probabilidad' => 0,
        ]);

        $this->actingAs($user)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ]);

        $riesgo->refresh();
        $this->assertEquals(6, $riesgo->probabilidad);
        $this->assertEquals(3, $riesgo->impacto);
    }

    #[Test]
    public function recalcular_deja_registro_del_cambio_en_el_historial(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $user->id,
            'impacto' => 0,
            'probabilidad' => 0,
        ]);

        $this->actingAs($user)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ]);

        $actualizacion = $riesgo->actualizaciones()->first();
        $this->assertEquals('edicion', $actualizacion->data['tipo']);
        $this->assertEquals(0, $actualizacion->data['diff']['campos']['impacto']['antes']);
        $this->assertEquals(3, $actualizacion->data['diff']['campos']['impacto']['despues']);
    }

    #[Test]
    public function no_se_puede_recalcular_un_riesgo_ya_aprobado(): void
    {
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->aprobado()->create(['user_id' => $user->id]);

        $respuesta = $this->actingAs($user)->get(route('auditoria.riesgos.recalcular', $riesgo));

        $respuesta->assertRedirect(route('auditoria.riesgos.show', $riesgo));
    }

    #[Test]
    public function el_comite_no_puede_recalcular_ni_siquiera_en_borrador(): void
    {
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create();

        $respuesta = $this->actingAs($comite)->get(route('auditoria.riesgos.recalcular', $riesgo));

        $respuesta->assertRedirect(route('auditoria.riesgos.show', $riesgo));
    }

    #[Test]
    public function un_gerente_recalcula_un_riesgo_ya_validado_y_se_aplica_al_toque(): void
    {
        // La propuesta arranca "validado" (estadoInicialParaCambio()) y el riesgo ya
        // está justo ahí: no hace falta que nadie más la valide, se aplica en el acto
        // — mismo criterio que un cambio de campo cualquiera vía GestionActualizaciones.
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->validado()->create([
            'user_id' => $gerente->id,
            'impacto' => 0,
            'probabilidad' => 0,
        ]);

        $respuesta = $this->actingAs($gerente)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ]);

        $respuesta->assertRedirect(route('auditoria.riesgos.show', $riesgo));

        $riesgo->refresh();
        $this->assertEquals(3, $riesgo->impacto);
        $this->assertEquals(6, $riesgo->probabilidad);
        $this->assertEquals('validado', $riesgo->estado->nombre);

        $actualizacion = Actualizacion::latest('id')->first();
        $this->assertEquals('validado', $actualizacion->estado->nombre);
        $this->assertEquals('cambio', $actualizacion->data['tipo']);
    }

    #[Test]
    public function un_empleado_recalcula_un_riesgo_validado_y_queda_pendiente(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $gerencia->id]);
        $riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $gerencia->id,
            'impacto' => 0,
            'probabilidad' => 0,
        ]);

        $respuesta = $this->actingAs($empleado)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ]);

        $respuesta->assertRedirect(route('auditoria.riesgos.show', $riesgo));

        // Un empleado arranca la propuesta en "borrador": no se aplica sola, necesita
        // que un gerente la valide (y, según el caso, el comité la apruebe).
        $riesgo->refresh();
        $this->assertEquals(0, $riesgo->impacto);
        $this->assertEquals(0, $riesgo->probabilidad);
        $this->assertEquals('validado', $riesgo->estado->nombre);

        $actualizacion = Actualizacion::latest('id')->first();
        $this->assertEquals('borrador', $actualizacion->estado->nombre);
        $this->assertEquals('cambio', $actualizacion->data['tipo']);
        $this->assertEquals(3, $actualizacion->data['campos']['impacto']);
    }

    #[Test]
    public function el_comite_no_puede_recalcular_por_post_directo_tampoco(): void
    {
        // El GET a recalcular() ya lo bloquea, pero el guard también vive en
        // recalcularStore(): un POST directo a la ruta no debe colarse.
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
        $riesgo = Riesgo::factory()->validado()->create(['impacto' => 0, 'probabilidad' => 0]);

        $respuesta = $this->actingAs($comite)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ]);

        $respuesta->assertRedirect(route('auditoria.riesgos.show', $riesgo));
        $this->assertEquals(0, $riesgo->fresh()->impacto);
    }
}
