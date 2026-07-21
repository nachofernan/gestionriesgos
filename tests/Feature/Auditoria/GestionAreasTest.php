<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Riesgo\Show\GestionAreas;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use App\Policies\Auditoria\RiesgoPolicy;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cubre la gestión de gerencias asociadas a un Riesgo desde su show (ver
 * app/Livewire/Auditoria/Riesgo/Show/GestionAreas.php): agregar/quitar en
 * memoria, el mínimo de una gerencia, y el bloqueo que sólo permite compartir
 * un riesgo ya validado y sólo a un gerente (ver RiesgoPolicy::gestionarGerencias).
 */
class GestionAreasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function riesgoValidado(Area $area): Riesgo
    {
        $riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $area->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
        $riesgo->areas()->sync([$area->id]);

        return $riesgo->load(['areas', 'estado']);
    }

    /**
     * Crea un riesgo en una sub-área (no gerencia) descendiente de una gerencia,
     * dejando que el observer de Riesgo sincronice area_riesgo (área puntual +
     * gerencia). $estado permite nacerlo en borrador o validado.
     */
    private function riesgoEnSubarea(Area $subarea, string $estado = 'borrador', ?User $creador = null): Riesgo
    {
        return Riesgo::factory()->{$estado}()->create([
            'area_id' => $subarea->id,
            'user_id' => $creador?->id ?? User::factory()->create()->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
    }

    /** @test */
    public function agregar_una_gerencia_la_suma_a_los_seleccionados(): void
    {
        $areaOriginal = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $areaNueva = Area::create(['nombre' => 'Gerencia B', 'tipo' => TipoArea::Gerencia]);
        $riesgo = $this->riesgoValidado($areaOriginal);
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => $areaOriginal->id]);

        Livewire::actingAs($user)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $areaNueva->id)
            ->call('guardar');

        $this->assertTrue($riesgo->refresh()->areas->pluck('id')->contains($areaNueva->id));
    }

    /** @test */
    public function no_se_puede_quitar_la_unica_gerencia_asociada(): void
    {
        $area = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $riesgo = $this->riesgoValidado($area);
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => $area->id]);

        Livewire::actingAs($user)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $area->id)
            ->assertSet('error', 'El riesgo debe tener al menos una gerencia asociada.')
            ->assertSet('seleccionados', [['id' => $area->id, 'nombre' => $area->nombre]]);

        $this->assertCount(1, $riesgo->refresh()->areas);
    }

    /** @test */
    public function crear_un_riesgo_en_subarea_asocia_el_area_puntual_y_su_gerencia(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $subarea = Area::create(['nombre' => 'Sistemas', 'area_padre_id' => $gerencia->id]);

        $riesgo = $this->riesgoEnSubarea($subarea);

        $ids = $riesgo->areas->pluck('id');
        $this->assertCount(2, $ids);
        $this->assertTrue($ids->contains($subarea->id));
        $this->assertTrue($ids->contains($gerencia->id));
    }

    /** @test */
    public function la_seccion_solo_muestra_la_gerencia_no_el_area_puntual(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $subarea = Area::create(['nombre' => 'Sistemas', 'area_padre_id' => $gerencia->id]);
        $riesgo = $this->riesgoEnSubarea($subarea);
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);

        Livewire::actingAs($user)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->assertSet('seleccionados', [['id' => $gerencia->id, 'nombre' => $gerencia->nombre]]);
    }

    /** @test */
    public function el_empleado_creador_conserva_acceso_a_su_borrador_en_subarea(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $subarea = Area::create(['nombre' => 'Sistemas', 'area_padre_id' => $gerencia->id]);
        $empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $subarea->id]);

        $riesgo = $this->riesgoEnSubarea($subarea, 'borrador', $empleado);
        $riesgo->load('areas');

        $this->assertTrue($riesgo->puedeGestionarAlgunaArea($empleado));
        $this->assertTrue((new RiesgoPolicy)->update($empleado, $riesgo));
    }

    /** @test */
    public function guardar_una_gerencia_nueva_preserva_el_area_puntual_oculta(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $subarea = Area::create(['nombre' => 'Sistemas', 'area_padre_id' => $gerencia->id]);
        $gerenciaDos = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $riesgo = $this->riesgoEnSubarea($subarea, 'validado');
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);

        Livewire::actingAs($user)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $gerenciaDos->id)
            ->call('guardar');

        $ids = $riesgo->refresh()->areas->pluck('id');
        $this->assertCount(3, $ids);
        $this->assertTrue($ids->contains($subarea->id), 'El área puntual oculta no debe eliminarse.');
        $this->assertTrue($ids->contains($gerencia->id));
        $this->assertTrue($ids->contains($gerenciaDos->id));
    }

    /** @test */
    public function el_buscador_de_agregar_no_devuelve_areas_que_no_son_gerencia(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $subarea = Area::create(['nombre' => 'Sistemas', 'area_padre_id' => $gerencia->id]);
        $riesgo = $this->riesgoEnSubarea($subarea, 'validado');
        $user = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);

        Livewire::actingAs($user)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('abrirModal')
            ->set('busqueda', 'Sistemas')
            ->assertViewHas('resultados', fn ($resultados) => $resultados->isEmpty());
    }

    // -------------------------------------------------------
    // Bloqueo: compartir sólo con el riesgo validado y sólo gerente
    // -------------------------------------------------------

    /** @test */
    public function gestionar_gerencias_requiere_gerente_y_riesgo_validado(): void
    {
        $area = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $area->id]);
        $empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $area->id]);
        $policy = new RiesgoPolicy;

        $borrador = Riesgo::factory()->borrador()->create([
            'area_id' => $area->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ])->load(['areas', 'estado']);
        $validado = $this->riesgoValidado($area);

        // El gerente sólo puede una vez validado, no en borrador.
        $this->assertFalse($policy->gestionarGerencias($gerente, $borrador));
        $this->assertTrue($policy->gestionarGerencias($gerente, $validado));

        // El empleado no puede nunca, ni siquiera validado.
        $this->assertFalse($policy->gestionarGerencias($empleado, $validado));
    }

    /** @test */
    public function puede_gestionar_refleja_estado_y_rol_en_el_componente(): void
    {
        $area = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $area->id]);

        $borrador = Riesgo::factory()->borrador()->create([
            'area_id' => $area->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
        $borrador->areas()->sync([$area->id]);

        Livewire::actingAs($gerente)
            ->test(GestionAreas::class, ['riesgo' => $borrador])
            ->assertSet('puedeGestionar', false);

        Livewire::actingAs($gerente)
            ->test(GestionAreas::class, ['riesgo' => $this->riesgoValidado($area)])
            ->assertSet('puedeGestionar', true);
    }
}
