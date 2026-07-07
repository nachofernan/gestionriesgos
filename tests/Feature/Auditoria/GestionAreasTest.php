<?php

namespace Tests\Feature\Auditoria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Models\User;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Livewire\Auditoria\Riesgo\Show\GestionAreas;
use Database\Seeders\EstadoRiesgoSeeder;

/**
 * Cubre la gestión de gerencias asociadas a un Riesgo desde su show (ver
 * app/Livewire/Auditoria/Riesgo/Show/GestionAreas.php): agregar/quitar en
 * memoria, el mínimo de una gerencia y el sync directo en borrador.
 */
class GestionAreasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function riesgoBorrador(Area $area): Riesgo
    {
        $riesgo = Riesgo::factory()->borrador()->create([
            'area_id'        => $area->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
        $riesgo->areas()->sync([$area->id]);

        return $riesgo;
    }

    /** @test */
    public function agregar_una_gerencia_la_suma_a_los_seleccionados(): void
    {
        $areaOriginal = Area::create(['nombre' => 'Gerencia A']);
        $areaNueva    = Area::create(['nombre' => 'Gerencia B']);
        $riesgo       = $this->riesgoBorrador($areaOriginal);
        $user         = User::factory()->create(['rol' => 'gerente', 'area_id' => $areaOriginal->id]);

        Livewire::actingAs($user)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $areaNueva->id)
            ->call('guardar');

        $riesgo->refresh();
        $this->assertCount(2, $riesgo->areas);
        $this->assertTrue($riesgo->areas->pluck('id')->contains($areaNueva->id));
    }

    /** @test */
    public function no_se_puede_quitar_la_unica_gerencia_asociada(): void
    {
        $area   = Area::create(['nombre' => 'Gerencia A']);
        $riesgo = $this->riesgoBorrador($area);
        $user   = User::factory()->create(['rol' => 'gerente', 'area_id' => $area->id]);

        Livewire::actingAs($user)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('quitar', $area->id)
            ->assertSet('error', 'El riesgo debe tener al menos una gerencia asociada.')
            ->assertSet('seleccionados', [['id' => $area->id, 'nombre' => $area->nombre]]);

        $riesgo->refresh();
        $this->assertCount(1, $riesgo->areas);
    }
}
