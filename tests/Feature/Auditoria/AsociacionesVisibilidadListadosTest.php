<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Control\Index\Search as ControlSearch;
use App\Livewire\Auditoria\Modal\DetalleRiesgo;
use App\Livewire\Auditoria\Objetivo\Index\Search as ObjetivoSearch;
use App\Livewire\Auditoria\PlanAccion\Index\Search as PlanSearch;
use App\Livewire\Auditoria\Riesgo\Index\Search as RiesgoSearch;
use App\Livewire\Auditoria\Tarea\Index\Search as TareaSearch;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mismo hueco que RiesgosAsociadosVisibilidadTest pero en los listados/búsqueda
 * (Index\Search) de cada entidad y en el modal de vista rápida DetalleRiesgo: la
 * columna con la entidad asociada no debe filtrar por visibilidad de por sí, así
 * que un borrador de otra gerencia no debe aparecer listado ahí (axioma 1 +
 * scopeVisiblePara), aunque sí para la propia gerencia. Reutiliza la jerarquía de
 * RiesgosAsociadosVisibilidadTest.
 */
class AsociacionesVisibilidadListadosTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerAdmin;

    private Area $gerProd;

    private Area $sectC;

    private User $canela;   // gerente de la gerencia A (gerAdmin)

    private User $grassi;   // gerente de la gerencia B (gerProd)

    private int $aprobadoId;

    private int $borradorId;

    private TipoRiesgo $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
        $this->tipo = TipoRiesgo::factory()->create();

        $comite = Area::create(['nombre' => 'Comité', 'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]);
        $this->gerProd = Area::create(['nombre' => 'Ger. Prod', 'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]);
        $this->sectC = Area::create(['nombre' => 'Sector C', 'area_padre_id' => $this->gerProd->id]);

        $this->canela = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerAdmin->id]);
        $this->grassi = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerProd->id]);

        $this->aprobadoId = Estado::aprobado()->id;
        $this->borradorId = Estado::borrador()->id;
    }

    private function riesgoEnB(int $estadoId): Riesgo
    {
        return Riesgo::create([
            'nombre' => 'R '.$estadoId.' '.uniqid(),
            'impacto' => 1,
            'probabilidad' => 1,
            'tipo_riesgo_id' => $this->tipo->id,
            'estado_id' => $estadoId,
            'area_id' => $this->sectC->id,
            'user_id' => $this->grassi->id,
        ]);
    }

    /** @test */
    public function el_listado_de_controles_no_expone_el_riesgo_borrador_de_b_a_un_gerente_de_a(): void
    {
        $control = Control::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $borrador = $this->riesgoEnB($this->borradorId);
        $aprobado = $this->riesgoEnB($this->aprobadoId);
        $control->riesgos()->sync([$borrador->id, $aprobado->id]);

        Livewire::actingAs($this->canela)
            ->test(ControlSearch::class)
            ->assertViewHas('controles', function ($controles) use ($control, $borrador, $aprobado) {
                $c = $controles->firstWhere('id', $control->id);

                return $c && ! $c->riesgos->pluck('id')->contains($borrador->id)
                    && $c->riesgos->pluck('id')->contains($aprobado->id);
            });
    }

    /** @test */
    public function el_listado_de_objetivos_no_expone_el_riesgo_borrador_de_b_a_un_gerente_de_a(): void
    {
        $objetivo = Objetivo::create([
            'nombre' => 'Obj', 'estado_id' => $this->aprobadoId,
            'area_id' => $this->sectC->id, 'user_id' => $this->grassi->id,
        ]);
        $borrador = $this->riesgoEnB($this->borradorId);
        $aprobado = $this->riesgoEnB($this->aprobadoId);
        $objetivo->riesgos()->sync([$borrador->id, $aprobado->id]);

        Livewire::actingAs($this->canela)
            ->test(ObjetivoSearch::class)
            ->assertViewHas('objetivos', function ($objetivos) use ($objetivo, $borrador, $aprobado) {
                $o = $objetivos->firstWhere('id', $objetivo->id);

                return $o && ! $o->riesgos->pluck('id')->contains($borrador->id)
                    && $o->riesgos->pluck('id')->contains($aprobado->id);
            });
    }

    /** @test */
    public function el_listado_de_planes_no_expone_el_riesgo_borrador_de_b_a_un_gerente_de_a(): void
    {
        $plan = PlanAccion::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $borrador = $this->riesgoEnB($this->borradorId);
        $aprobado = $this->riesgoEnB($this->aprobadoId);
        $plan->riesgos()->sync([$borrador->id, $aprobado->id]);

        Livewire::actingAs($this->canela)
            ->test(PlanSearch::class)
            ->assertViewHas('planes', function ($planes) use ($plan, $borrador, $aprobado) {
                $p = $planes->firstWhere('id', $plan->id);

                return $p && ! $p->riesgos->pluck('id')->contains($borrador->id)
                    && $p->riesgos->pluck('id')->contains($aprobado->id);
            });
    }

    /** @test */
    public function el_listado_de_riesgos_no_expone_el_objetivo_borrador_de_b_a_un_gerente_de_a(): void
    {
        $riesgo = $this->riesgoEnB($this->aprobadoId);
        $objBorrador = Objetivo::create([
            'nombre' => 'Obj borrador', 'estado_id' => $this->borradorId,
            'area_id' => $this->sectC->id, 'user_id' => $this->grassi->id,
        ]);
        $objAprobado = Objetivo::create([
            'nombre' => 'Obj aprobado', 'estado_id' => $this->aprobadoId,
            'area_id' => $this->sectC->id, 'user_id' => $this->grassi->id,
        ]);
        $riesgo->objetivos()->sync([$objBorrador->id, $objAprobado->id]);

        Livewire::actingAs($this->canela)
            ->test(RiesgoSearch::class)
            ->assertViewHas('riesgos', function ($riesgos) use ($riesgo, $objBorrador, $objAprobado) {
                $r = $riesgos->firstWhere('id', $riesgo->id);

                return $r && ! $r->objetivos->pluck('id')->contains($objBorrador->id)
                    && $r->objetivos->pluck('id')->contains($objAprobado->id);
            });
    }

    /** @test */
    public function el_listado_de_tareas_no_expone_el_plan_borrador_de_b_a_un_gerente_de_a(): void
    {
        $tarea = Tarea::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $planBorrador = PlanAccion::factory()->create(['estado_id' => $this->borradorId, 'area_id' => $this->sectC->id]);
        $planAprobado = PlanAccion::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $tarea->planesAccion()->sync([$planBorrador->id, $planAprobado->id]);

        Livewire::actingAs($this->canela)
            ->test(TareaSearch::class)
            ->assertViewHas('tareas', function ($tareas) use ($tarea, $planBorrador, $planAprobado) {
                $t = $tareas->firstWhere('id', $tarea->id);

                return $t && ! $t->planesAccion->pluck('id')->contains($planBorrador->id)
                    && $t->planesAccion->pluck('id')->contains($planAprobado->id);
            });
    }

    /** @test */
    public function el_modal_detalle_riesgo_no_expone_el_control_borrador_de_b_a_un_gerente_de_a(): void
    {
        $riesgo = $this->riesgoEnB($this->aprobadoId);
        $ctrlBorrador = Control::factory()->create(['estado_id' => $this->borradorId, 'area_id' => $this->sectC->id]);
        $ctrlAprobado = Control::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $riesgo->controles()->sync([$ctrlBorrador->id, $ctrlAprobado->id]);

        $ids = Livewire::actingAs($this->canela)
            ->test(DetalleRiesgo::class)
            ->call('abrir', $riesgo->id)
            ->instance()->riesgo->controles->pluck('id')->all();

        $this->assertNotContains($ctrlBorrador->id, $ids);
        $this->assertContains($ctrlAprobado->id, $ids);
    }

    /** @test */
    public function el_modal_detalle_riesgo_no_expone_el_control_borrado_de_nadie(): void
    {
        $riesgo = $this->riesgoEnB($this->aprobadoId);
        $ctrlBorrado = Control::factory()->create(['estado_id' => Estado::borrado()->id, 'area_id' => $this->sectC->id]);
        $ctrlAprobado = Control::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $riesgo->controles()->sync([$ctrlBorrado->id, $ctrlAprobado->id]);

        $ids = Livewire::actingAs($this->grassi)
            ->test(DetalleRiesgo::class)
            ->call('abrir', $riesgo->id)
            ->instance()->riesgo->controles->pluck('id')->all();

        $this->assertNotContains($ctrlBorrado->id, $ids, 'Un control borrado queda en limbo, ni siquiera su propia gerencia lo ve.');
        $this->assertContains($ctrlAprobado->id, $ids);
    }
}
