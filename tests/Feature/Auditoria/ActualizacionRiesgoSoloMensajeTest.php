<?php

namespace Tests\Feature\Auditoria;

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
use Tests\TestCase;

/**
 * Por decisión de producto, la actualización de un riesgo es solo mensaje + adjunto:
 * impacto y probabilidad los calcula el wizard y nombre/descripción se resolverán con
 * otro mecanismo, así que el modal no ofrece campos editables para un riesgo.
 */
class ActualizacionRiesgoSoloMensajeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function riesgoValidado(Area $gerencia): Riesgo
    {
        return Riesgo::factory()->validado()->create([
            'area_id' => $gerencia->id,
            'nombre' => 'Riesgo original',
            'descripcion' => 'Descripción original',
            'impacto' => 4,
            'probabilidad' => 3,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
    }

    /** @test */
    public function el_modal_de_actualizacion_de_un_riesgo_no_ofrece_campos_editables(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);
        $riesgo = $this->riesgoValidado($gerencia);

        Livewire::actingAs($gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->assertViewHas('camposEditables', [])
            ->assertSet('cambios', []);
    }

    /** @test */
    public function una_actualizacion_de_riesgo_crea_solo_el_mensaje_sin_tocar_impacto_ni_probabilidad(): void
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
        $this->assertEquals('cambio', $actualizacion->data['tipo']);
        $this->assertArrayNotHasKey('campos', $actualizacion->data);
        $this->assertArrayNotHasKey('diff', $actualizacion->data);

        // El riesgo queda intacto: ningún campo se editó desde el modal.
        $riesgo->refresh();
        $this->assertEquals('Riesgo original', $riesgo->nombre);
        $this->assertEquals('Descripción original', $riesgo->descripcion);
        $this->assertEquals(4, $riesgo->impacto);
        $this->assertEquals(3, $riesgo->probabilidad);
    }
}
