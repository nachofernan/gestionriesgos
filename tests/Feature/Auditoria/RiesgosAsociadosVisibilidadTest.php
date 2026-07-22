<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
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
use Tests\TestCase;

/**
 * Fuga de visibilidad: un riesgo en estado borrador de una gerencia B no debe
 * aparecer como fila en el listado "Riesgos asociados" de las pantallas show de
 * otras entidades (plan, control, objetivo, tarea) cuando la mira un gerente de
 * la gerencia A que no gestiona B. Verifica el eager-load acotado por
 * scopeVisiblePara en los controladores show (axiomas 1 y 3).
 *
 * Jerarquía:
 *   comite (raíz)
 *   ├── gerAdmin (gerencia A)  ← canela (gerente)
 *   │   └── sectA              ← nacho  (empleado)
 *   └── gerProd  (gerencia B)  ← grassi (gerente)
 *       └── sectC              ← nocetti (empleado)
 */
class RiesgosAsociadosVisibilidadTest extends TestCase
{
    use RefreshDatabase;

    private Area $comite;

    private Area $gerAdmin;

    private Area $gerProd;

    private Area $sectC;

    private User $canela;   // gerente de la gerencia A (gerAdmin)

    private User $grassi;   // gerente de la gerencia B (gerProd)

    private TipoRiesgo $tipo;

    private int $aprobadoId;

    private int $borradorId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EstadoRiesgoSeeder::class);

        $this->comite = Area::create(['nombre' => 'Comité', 'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $this->comite->id, 'tipo' => TipoArea::Gerencia]);
        $this->gerProd = Area::create(['nombre' => 'Ger. Prod', 'area_padre_id' => $this->comite->id, 'tipo' => TipoArea::Gerencia]);
        $this->sectC = Area::create(['nombre' => 'Sector C', 'area_padre_id' => $this->gerProd->id]);

        $this->canela = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerAdmin->id]);
        $this->grassi = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerProd->id]);

        $this->tipo = TipoRiesgo::factory()->create();

        $this->aprobadoId = Estado::aprobado()->id;
        $this->borradorId = Estado::borrador()->id;
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /** Crea un riesgo en sectC (gerencia B) con el estado dado. */
    private function riesgoEnB(int $estadoId): Riesgo
    {
        return Riesgo::create([
            'nombre' => 'R '.$estadoId,
            'impacto' => 1,
            'probabilidad' => 1,
            'tipo_riesgo_id' => $this->tipo->id,
            'estado_id' => $estadoId,
            'area_id' => $this->sectC->id,
            'user_id' => $this->grassi->id,
        ]);
    }

    /** IDs de la colección `riesgos` que la vista show del plan recibe para $user. */
    private function riesgosDelPlan(PlanAccion $plan, User $user): array
    {
        return $this->actingAs($user)
            ->get(route('auditoria.planes.show', $plan))
            ->assertOk()
            ->viewData('planAccion')->riesgos->pluck('id')->all();
    }

    // -------------------------------------------------------
    // Plan de acción
    // -------------------------------------------------------

    /** @test */
    public function plan_publico_de_b_no_expone_su_riesgo_borrador_a_un_gerente_de_a(): void
    {
        $plan = PlanAccion::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $borrador = $this->riesgoEnB($this->borradorId);
        $aprobado = $this->riesgoEnB($this->aprobadoId);
        $plan->riesgos()->sync([$borrador->id, $aprobado->id]);

        $ids = $this->riesgosDelPlan($plan, $this->canela);

        $this->assertNotContains($borrador->id, $ids, 'El borrador de B no debe aparecer para un gerente de A.');
        $this->assertContains($aprobado->id, $ids, 'El aprobado de B sí debe aparecer para un gerente de A.');
    }

    /** @test */
    public function plan_publico_de_b_muestra_su_riesgo_borrador_al_propio_gerente_de_b(): void
    {
        $plan = PlanAccion::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $borrador = $this->riesgoEnB($this->borradorId);
        $plan->riesgos()->sync([$borrador->id]);

        $ids = $this->riesgosDelPlan($plan, $this->grassi);

        $this->assertContains($borrador->id, $ids, 'El gerente de B sí ve su propio borrador asociado.');
    }

    // -------------------------------------------------------
    // Control
    // -------------------------------------------------------

    /** @test */
    public function control_publico_de_b_no_expone_su_riesgo_borrador_a_un_gerente_de_a(): void
    {
        $control = Control::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $borrador = $this->riesgoEnB($this->borradorId);
        $aprobado = $this->riesgoEnB($this->aprobadoId);
        $control->riesgos()->sync([$borrador->id, $aprobado->id]);

        $ids = $this->actingAs($this->canela)
            ->get(route('auditoria.controles.show', $control))
            ->assertOk()
            ->viewData('control')->riesgos->pluck('id')->all();

        $this->assertNotContains($borrador->id, $ids);
        $this->assertContains($aprobado->id, $ids);
    }

    /** @test */
    public function control_publico_de_b_muestra_su_riesgo_borrador_al_propio_gerente_de_b(): void
    {
        $control = Control::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $borrador = $this->riesgoEnB($this->borradorId);
        $control->riesgos()->sync([$borrador->id]);

        $ids = $this->actingAs($this->grassi)
            ->get(route('auditoria.controles.show', $control))
            ->assertOk()
            ->viewData('control')->riesgos->pluck('id')->all();

        $this->assertContains($borrador->id, $ids);
    }

    // -------------------------------------------------------
    // Objetivo
    // -------------------------------------------------------

    /** @test */
    public function objetivo_publico_de_b_no_expone_su_riesgo_borrador_a_un_gerente_de_a(): void
    {
        $objetivo = Objetivo::create([
            'nombre' => 'Obj',
            'estado_id' => $this->aprobadoId,
            'area_id' => $this->sectC->id,
            'user_id' => $this->grassi->id,
        ]);
        $borrador = $this->riesgoEnB($this->borradorId);
        $aprobado = $this->riesgoEnB($this->aprobadoId);
        $objetivo->riesgos()->sync([$borrador->id, $aprobado->id]);

        $ids = $this->actingAs($this->canela)
            ->get(route('auditoria.objetivos.show', $objetivo))
            ->assertOk()
            ->viewData('objetivo')->riesgos->pluck('id')->all();

        $this->assertNotContains($borrador->id, $ids);
        $this->assertContains($aprobado->id, $ids);
    }

    /** @test */
    public function objetivo_publico_de_b_muestra_su_riesgo_borrador_al_propio_gerente_de_b(): void
    {
        $objetivo = Objetivo::create([
            'nombre' => 'Obj',
            'estado_id' => $this->aprobadoId,
            'area_id' => $this->sectC->id,
            'user_id' => $this->grassi->id,
        ]);
        $borrador = $this->riesgoEnB($this->borradorId);
        $objetivo->riesgos()->sync([$borrador->id]);

        $ids = $this->actingAs($this->grassi)
            ->get(route('auditoria.objetivos.show', $objetivo))
            ->assertOk()
            ->viewData('objetivo')->riesgos->pluck('id')->all();

        $this->assertContains($borrador->id, $ids);
    }

    // -------------------------------------------------------
    // Tarea (los riesgos cuelgan de planesAccion.riesgos)
    // -------------------------------------------------------

    /** @test */
    public function tarea_publica_de_b_no_expone_el_riesgo_borrador_de_su_plan_a_un_gerente_de_a(): void
    {
        $tarea = Tarea::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $plan = PlanAccion::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $tarea->planesAccion()->sync([$plan->id]);

        $borrador = $this->riesgoEnB($this->borradorId);
        $aprobado = $this->riesgoEnB($this->aprobadoId);
        $plan->riesgos()->sync([$borrador->id, $aprobado->id]);

        $ids = $this->actingAs($this->canela)
            ->get(route('auditoria.tareas.show', $tarea))
            ->assertOk()
            ->viewData('tarea')->planesAccion->flatMap->riesgos->pluck('id')->all();

        $this->assertNotContains($borrador->id, $ids);
        $this->assertContains($aprobado->id, $ids);
    }

    /** @test */
    public function tarea_publica_de_b_muestra_el_riesgo_borrador_de_su_plan_al_propio_gerente_de_b(): void
    {
        $tarea = Tarea::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $plan = PlanAccion::factory()->create(['estado_id' => $this->aprobadoId, 'area_id' => $this->sectC->id]);
        $tarea->planesAccion()->sync([$plan->id]);

        $borrador = $this->riesgoEnB($this->borradorId);
        $plan->riesgos()->sync([$borrador->id]);

        $ids = $this->actingAs($this->grassi)
            ->get(route('auditoria.tareas.show', $tarea))
            ->assertOk()
            ->viewData('tarea')->planesAccion->flatMap->riesgos->pluck('id')->all();

        $this->assertContains($borrador->id, $ids);
    }
}
