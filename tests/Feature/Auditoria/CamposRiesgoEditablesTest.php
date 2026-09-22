<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un riesgo puede editarse durante toda su vida (no sólo en borrador) a través
 * del modal de Actualizaciones: nombre, descripción, respuesta, fundamento y
 * tipo de riesgo. Impacto/probabilidad quedan afuera (van por el wizard de
 * recálculo). Reemplaza la decisión anterior de que el modal de un riesgo sólo
 * admitía mensaje + adjunto (ver docs/DECISIONES.md).
 */
class CamposRiesgoEditablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function riesgoValidado(Area $gerencia, array $overrides = []): Riesgo
    {
        return Riesgo::factory()->validado()->create(array_merge([
            'area_id' => $gerencia->id,
            'nombre' => 'Riesgo original',
            'descripcion' => 'Descripción original',
            'impacto' => 4,
            'probabilidad' => 3,
            'respuesta' => RespuestaRiesgo::Aceptar,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ], $overrides));
    }

    #[Test]
    public function el_modal_de_actualizacion_de_un_riesgo_ofrece_los_campos_editables(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);
        $riesgo = $this->riesgoValidado($gerencia);

        Livewire::actingAs($gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->assertViewHas('camposEditables', fn ($campos) => array_keys($campos) === [
                'nombre', 'descripcion', 'respuesta', 'fundamento', 'tipo_riesgo_id',
            ]);
    }

    #[Test]
    public function un_gerente_puede_proponer_un_cambio_de_nombre_sobre_un_riesgo_aprobado(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);
        $riesgo = Riesgo::factory()->aprobado()->create([
            'area_id' => $gerencia->id,
            'nombre' => 'Riesgo original',
            'respuesta' => RespuestaRiesgo::Aceptar,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);

        Livewire::actingAs($gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Corrijo el título')
            ->set('cambios.nombre', 'Nombre corregido')
            ->call('guardar')
            ->assertHasNoErrors();

        // Un gerente sobre un riesgo aprobado: la propuesta arranca "validado"
        // (estadoParaActualizacion()) y necesita al comité para aplicarse. El riesgo
        // mismo sigue aprobado, no retrocede.
        $actualizacion = Actualizacion::latest('id')->first();
        $this->assertEquals('validado', $actualizacion->estado->nombre);
        $this->assertEquals('Riesgo original', $riesgo->fresh()->nombre);
        $this->assertEquals('aprobado', $riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function el_comite_aplica_al_toque_un_cambio_sobre_un_riesgo_aprobado(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
        $riesgo = Riesgo::factory()->aprobado()->create([
            'area_id' => $gerencia->id,
            'nombre' => 'Riesgo original',
            'respuesta' => RespuestaRiesgo::Aceptar,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);

        Livewire::actingAs($comite)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Corrijo el título')
            ->set('cambios.nombre', 'Nombre corregido')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertEquals('Nombre corregido', $riesgo->fresh()->nombre);
        $this->assertEquals('aprobado', $riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function no_admite_cambiar_la_respuesta_a_compartir_en_un_tipo_de_riesgo_restringido(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);
        $tipoRestringido = TipoRiesgo::factory()->create(['restringe_respuesta' => true]);
        $riesgo = $this->riesgoValidado($gerencia, ['tipo_riesgo_id' => $tipoRestringido->id]);

        Livewire::actingAs($gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Cambio la respuesta')
            ->set('cambios.respuesta', 'compartir')
            ->call('guardar')
            ->assertHasErrors('cambios.respuesta');
    }

    #[Test]
    public function exige_fundamento_si_la_nueva_respuesta_lo_requiere(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);
        $riesgo = $this->riesgoValidado($gerencia, ['respuesta' => RespuestaRiesgo::Evitar, 'fundamento' => null]);

        Livewire::actingAs($gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Cambio la respuesta')
            ->set('cambios.respuesta', 'aceptar')
            ->call('guardar')
            ->assertHasErrors('cambios.fundamento');
    }

    #[Test]
    public function una_actualizacion_de_riesgo_sin_cambios_de_campo_crea_solo_el_mensaje(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);
        $riesgo = $this->riesgoValidado($gerencia);

        Livewire::actingAs($gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Novedad sin cambios de campos')
            ->call('guardar')
            ->assertHasNoErrors();

        $actualizacion = Actualizacion::latest('id')->first();
        $this->assertNotNull($actualizacion);
        $this->assertEquals('Novedad sin cambios de campos', $actualizacion->mensaje);
        // Mensaje puro: sin cambios de campo, no entra al ciclo de validación.
        $this->assertNull($actualizacion->data);
        $this->assertNull($actualizacion->estado_id);

        // El riesgo queda intacto: ningún campo se editó desde el modal.
        $riesgo->refresh();
        $this->assertEquals('Riesgo original', $riesgo->nombre);
        $this->assertEquals('Descripción original', $riesgo->descripcion);
        $this->assertEquals(4, $riesgo->impacto);
        $this->assertEquals(3, $riesgo->probabilidad);
    }
}
