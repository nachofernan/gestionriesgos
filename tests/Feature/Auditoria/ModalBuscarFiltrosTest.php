<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Riesgo\Show\GestionControles;
use App\Livewire\Auditoria\Riesgo\Show\GestionObjetivos;
use App\Livewire\Auditoria\Riesgo\Show\GestionPlanes;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Núcleo sagrado (Axioma 1 + scopeVisiblePara): los filtros del modal "Agregar
 * control/plan/objetivo" (trait BuscaEnModal) sólo restringen lo visible, nunca lo
 * amplían. Un gerente puede filtrar por la gerencia ajena o por "Todas" y aun así
 * no ve los borradores de otra gerencia. Cubre los tres modales con la misma regla.
 */
class ModalBuscarFiltrosTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerAdmin;

    private Area $gerProd;

    private User $canela;   // gerente de Admin

    private User $comite;

    private Riesgo $riesgo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'tipo' => TipoArea::Gerencia]);
        $this->gerProd = Area::create(['nombre' => 'Ger. Prod', 'tipo' => TipoArea::Gerencia]);
        $this->canela = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerAdmin->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        $this->riesgo = Riesgo::factory()->create([
            'area_id' => $this->gerAdmin->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
    }

    /** Un elemento de cada tipo con el mismo nombre, en el área y estado dados. */
    private function elementos(string $nombre, Area $area, Estado $estado): array
    {
        return [
            'controles' => Control::factory()->create(['nombre' => $nombre, 'area_id' => $area->id, 'estado_id' => $estado->id]),
            'planes' => PlanAccion::factory()->create(['nombre' => $nombre, 'area_id' => $area->id, 'estado_id' => $estado->id]),
            'objetivos' => Objetivo::create(['nombre' => $nombre, 'area_id' => $area->id, 'estado_id' => $estado->id, 'user_id' => $this->canela->id]),
        ];
    }

    private function componentes(): array
    {
        return [
            'controles' => GestionControles::class,
            'planes' => GestionPlanes::class,
            'objetivos' => GestionObjetivos::class,
        ];
    }

    private function idsResultados($componente): array
    {
        return $componente->viewData('resultados')->pluck('id')->all();
    }

    #[Test]
    public function el_modal_abre_mostrando_solo_lo_propio_del_gerente(): void
    {
        $propio = $this->elementos('Propio', $this->gerAdmin, Estado::borrador());
        $ajenoAprobado = $this->elementos('Ajeno', $this->gerProd, Estado::aprobado());

        foreach ($this->componentes() as $clave => $clase) {
            $c = Livewire::actingAs($this->canela)->test($clase, ['riesgo' => $this->riesgo])->call('abrirModal');

            $this->assertSame($this->gerAdmin->id, $c->get('filtroArea'));
            $this->assertContains($propio[$clave]->id, $this->idsResultados($c), $clave);
            $this->assertNotContains($ajenoAprobado[$clave]->id, $this->idsResultados($c), $clave);
        }
    }

    #[Test]
    public function elegir_todas_suma_lo_publico_ajeno_pero_nunca_un_borrador_ajeno(): void
    {
        $ajenoAprobado = $this->elementos('Ajeno aprobado', $this->gerProd, Estado::aprobado());
        $ajenoBorrador = $this->elementos('Ajeno borrador', $this->gerProd, Estado::borrador());

        foreach ($this->componentes() as $clave => $clase) {
            $c = Livewire::actingAs($this->canela)->test($clase, ['riesgo' => $this->riesgo])
                ->call('abrirModal')->set('filtroArea', null);

            $this->assertContains($ajenoAprobado[$clave]->id, $this->idsResultados($c), $clave);
            $this->assertNotContains($ajenoBorrador[$clave]->id, $this->idsResultados($c), $clave);
        }
    }

    #[Test]
    public function filtrar_por_la_gerencia_ajena_no_expone_su_borrador(): void
    {
        $ajenoAprobado = $this->elementos('Ajeno aprobado', $this->gerProd, Estado::aprobado());
        $ajenoBorrador = $this->elementos('Ajeno borrador', $this->gerProd, Estado::borrador());

        foreach ($this->componentes() as $clave => $clase) {
            $c = Livewire::actingAs($this->canela)->test($clase, ['riesgo' => $this->riesgo])
                ->call('abrirModal')->set('filtroArea', $this->gerProd->id);

            $this->assertContains($ajenoAprobado[$clave]->id, $this->idsResultados($c), $clave);
            $this->assertNotContains($ajenoBorrador[$clave]->id, $this->idsResultados($c), $clave);

            // Forzando el estado borrador sobre la gerencia ajena tampoco aparece.
            $c->set('filtroEstado', Estado::borrador()->id);
            $this->assertNotContains($ajenoBorrador[$clave]->id, $this->idsResultados($c), $clave);
        }
    }

    #[Test]
    public function los_filtros_de_estado_y_area_se_combinan_con_la_busqueda_por_nombre(): void
    {
        $aprobado = $this->elementos('Gestión de stock', $this->gerAdmin, Estado::aprobado());
        $borrador = $this->elementos('Gestión de compras', $this->gerAdmin, Estado::borrador());

        foreach ($this->componentes() as $clave => $clase) {
            $c = Livewire::actingAs($this->canela)->test($clase, ['riesgo' => $this->riesgo])
                ->call('abrirModal')->set('filtroEstado', Estado::aprobado()->id);
            $this->assertSame([$aprobado[$clave]->id], $this->idsResultados($c), $clave);

            $c->set('filtroEstado', null)->set('busqueda', 'compras');
            $this->assertSame([$borrador[$clave]->id], $this->idsResultados($c), $clave);
        }
    }

    #[Test]
    public function el_comite_abre_el_modal_sin_filtro_de_area(): void
    {
        $ajeno = $this->elementos('Ajeno', $this->gerProd, Estado::aprobado());

        foreach ($this->componentes() as $clave => $clase) {
            $c = Livewire::actingAs($this->comite)->test($clase, ['riesgo' => $this->riesgo])->call('abrirModal');

            $this->assertNull($c->get('filtroArea'));
            $this->assertContains($ajeno[$clave]->id, $this->idsResultados($c), $clave);
        }
    }
}
