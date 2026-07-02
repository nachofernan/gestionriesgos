<?php

namespace Tests\Feature\Auditoria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\TipoRiesgo;
use App\Policies\Auditoria\RiesgoPolicy;
use Database\Seeders\EstadoRiesgoSeeder;

/**
 * Verifica las reglas de visibilidad jerárquica por rol y estado.
 *
 * Jerarquía usada en los tests:
 *   comite (raíz)
 *   ├── gerAdmin
 *   │   ├── sectA   ← nacho (empleado)
 *   │   └── sectB   ← tito  (empleado, hermano de nacho)
 *   └── gerProd
 *       └── sectC   ← nocetti (empleado)
 *
 * Reglas:
 *   activo   → todos lo ven
 *   validado → comité=todos, gerente=su subtree, empleado=su gerencia completa
 *   borrador → comité=nadie, gerente=su subtree, empleado=solo su área propia
 */
class VisibilidadTest extends TestCase
{
    use RefreshDatabase;

    private Area $comite;
    private Area $gerAdmin;
    private Area $gerProd;
    private Area $sectA;
    private Area $sectB;
    private Area $sectC;

    private User $lucia;    // comité   – comite
    private User $canela;   // gerente  – gerAdmin
    private User $nacho;    // empleado – sectA
    private User $tito;     // empleado – sectB (hermano de nacho)
    private User $grassi;   // gerente  – gerProd
    private User $nocetti;  // empleado – sectC

    private int $borradorId;
    private int $validadoId;
    private int $activoId;
    private int $borradoId;
    private TipoRiesgo $tipo;

    // -------------------------------------------------------
    // Setup
    // -------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EstadoRiesgoSeeder::class);

        $this->comite   = Area::create(['nombre' => 'Comité',     'area_padre_id' => null]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $this->comite->id]);
        $this->gerProd  = Area::create(['nombre' => 'Ger. Prod',  'area_padre_id' => $this->comite->id]);
        $this->sectA    = Area::create(['nombre' => 'Sector A',   'area_padre_id' => $this->gerAdmin->id]);
        $this->sectB    = Area::create(['nombre' => 'Sector B',   'area_padre_id' => $this->gerAdmin->id]);
        $this->sectC    = Area::create(['nombre' => 'Sector C',   'area_padre_id' => $this->gerProd->id]);

        $this->lucia   = User::factory()->create(['rol' => 'comite',   'area_id' => $this->comite->id]);
        $this->canela  = User::factory()->create(['rol' => 'gerente',  'area_id' => $this->gerAdmin->id]);
        $this->nacho   = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectA->id]);
        $this->tito    = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectB->id]);
        $this->grassi  = User::factory()->create(['rol' => 'gerente',  'area_id' => $this->gerProd->id]);
        $this->nocetti = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectC->id]);

        $this->borradorId = Estado::borrador()->id;
        $this->validadoId = Estado::validado()->id;
        $this->activoId   = Estado::activo()->id;
        $this->borradoId  = Estado::borrado()->id;

        $this->tipo = TipoRiesgo::factory()->create();
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function riesgo(int $estadoId, Area $area): Riesgo
    {
        return Riesgo::create([
            'nombre'         => 'R',
            'impacto'        => 1,
            'probabilidad'   => 1,
            'tipo_riesgo_id' => $this->tipo->id,
            'estado_id'      => $estadoId,
            'area_id'        => $area->id,
            'user_id'        => $this->nacho->id,
        ]);
    }

    private function control(int $estadoId, Area $area): Control
    {
        return Control::create([
            'nombre'             => 'C',
            'mitigacion_default' => 5,
            'estado_id'          => $estadoId,
            'area_id'            => $area->id,
            'user_id'            => $this->nacho->id,
        ]);
    }

    private function idsVisibles(User $user): array
    {
        return Riesgo::visiblePara($user)->pluck('id')->toArray();
    }

    private function policy(): RiesgoPolicy
    {
        return new RiesgoPolicy();
    }

    // -------------------------------------------------------
    // Helper: areaGerencia()
    // -------------------------------------------------------

    /** @test */
    public function area_gerencia_de_empleado_devuelve_la_gerencia_padre(): void
    {
        // nacho → sectA → gerAdmin (nivel 1 bajo raíz)
        $this->assertEquals($this->gerAdmin->id, $this->nacho->areaGerencia()->id);
    }

    /** @test */
    public function area_gerencia_de_gerente_es_su_propia_area(): void
    {
        // canela → gerAdmin (nivel 1 bajo raíz, ya es la gerencia)
        $this->assertEquals($this->gerAdmin->id, $this->canela->areaGerencia()->id);
    }

    /** @test */
    public function area_gerencia_de_empleado_en_otra_gerencia_es_correcta(): void
    {
        $this->assertEquals($this->gerProd->id, $this->nocetti->areaGerencia()->id);
    }

    // -------------------------------------------------------
    // Scope: comité
    // -------------------------------------------------------

    /** @test */
    public function comite_ve_activo_de_cualquier_gerencia(): void
    {
        $r = $this->riesgo($this->activoId, $this->sectC);
        $this->assertContains($r->id, $this->idsVisibles($this->lucia));
    }

    /** @test */
    public function comite_ve_validado_de_cualquier_gerencia(): void
    {
        $r = $this->riesgo($this->validadoId, $this->sectC);
        $this->assertContains($r->id, $this->idsVisibles($this->lucia));
    }

    /** @test */
    public function comite_no_ve_borrador(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectA);
        $this->assertNotContains($r->id, $this->idsVisibles($this->lucia));
    }

    /** @test */
    public function comite_no_ve_borrado(): void
    {
        $r = $this->riesgo($this->borradoId, $this->sectA);
        $this->assertNotContains($r->id, $this->idsVisibles($this->lucia));
    }

    // -------------------------------------------------------
    // Scope: gerente
    // -------------------------------------------------------

    /** @test */
    public function gerente_ve_activo_de_otra_gerencia(): void
    {
        $r = $this->riesgo($this->activoId, $this->sectC); // gerProd
        $this->assertContains($r->id, $this->idsVisibles($this->canela));
    }

    /** @test */
    public function gerente_ve_validado_de_su_gerencia(): void
    {
        $r = $this->riesgo($this->validadoId, $this->sectA);
        $this->assertContains($r->id, $this->idsVisibles($this->canela));
    }

    /** @test */
    public function gerente_ve_borrador_de_su_gerencia(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectB);
        $this->assertContains($r->id, $this->idsVisibles($this->canela));
    }

    /** @test */
    public function gerente_no_ve_validado_de_otra_gerencia(): void
    {
        $r = $this->riesgo($this->validadoId, $this->sectC);
        $this->assertNotContains($r->id, $this->idsVisibles($this->canela));
    }

    /** @test */
    public function gerente_no_ve_borrador_de_otra_gerencia(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectC);
        $this->assertNotContains($r->id, $this->idsVisibles($this->canela));
    }

    // -------------------------------------------------------
    // Scope: empleado
    // -------------------------------------------------------

    /** @test */
    public function empleado_ve_activo_de_otra_gerencia(): void
    {
        $r = $this->riesgo($this->activoId, $this->sectC);
        $this->assertContains($r->id, $this->idsVisibles($this->nacho));
    }

    /** @test */
    public function empleado_ve_validado_de_su_propia_area(): void
    {
        $r = $this->riesgo($this->validadoId, $this->sectA);
        $this->assertContains($r->id, $this->idsVisibles($this->nacho));
    }

    /** @test */
    public function empleado_ve_validado_de_sector_hermano(): void
    {
        // sectB comparte padre (gerAdmin) con sectA donde está nacho
        $r = $this->riesgo($this->validadoId, $this->sectB);
        $this->assertContains($r->id, $this->idsVisibles($this->nacho));
    }

    /** @test */
    public function empleado_no_ve_validado_de_otra_gerencia(): void
    {
        $r = $this->riesgo($this->validadoId, $this->sectC);
        $this->assertNotContains($r->id, $this->idsVisibles($this->nacho));
    }

    /** @test */
    public function empleado_ve_borrador_de_su_area(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectA);
        $this->assertContains($r->id, $this->idsVisibles($this->nacho));
    }

    /** @test */
    public function empleado_no_ve_borrador_de_sector_hermano(): void
    {
        // sectB es hermano de sectA — nacho no puede ver borradores de sectB
        $r = $this->riesgo($this->borradorId, $this->sectB);
        $this->assertNotContains($r->id, $this->idsVisibles($this->nacho));
    }

    /** @test */
    public function empleado_no_ve_borrador_de_otra_gerencia(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectC);
        $this->assertNotContains($r->id, $this->idsVisibles($this->nacho));
    }

    // -------------------------------------------------------
    // Scope: casos borde
    // -------------------------------------------------------

    /** @test */
    public function superadmin_sin_area_ve_todo_incluyendo_borradores(): void
    {
        $admin = User::factory()->create(['area_id' => null, 'rol' => 'empleado']);
        $r1    = $this->riesgo($this->borradorId, $this->sectA);
        $r2    = $this->riesgo($this->borradorId, $this->sectC);

        $ids = Riesgo::visiblePara($admin)->pluck('id')->toArray();
        $this->assertContains($r1->id, $ids);
        $this->assertContains($r2->id, $ids);
    }

    /** @test */
    public function scope_funciona_en_control_igual_que_en_riesgo(): void
    {
        $visible   = $this->control($this->activoId,   $this->sectC);
        $invisible = $this->control($this->borradorId, $this->sectB); // hermano de nacho

        $ids = Control::visiblePara($this->nacho)->pluck('id')->toArray();
        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($invisible->id, $ids);
    }

    /** @test */
    public function scope_combina_correctamente_con_filtros_adicionales(): void
    {
        $r1 = $this->riesgo($this->activoId,   $this->sectA);
        $r2 = $this->riesgo($this->borradorId, $this->sectA);
        $r3 = $this->riesgo($this->activoId,   $this->sectC);

        // Nacho filtra además por su propia área
        $ids = Riesgo::visiblePara($this->nacho)
            ->where('area_id', $this->sectA->id)
            ->pluck('id')->toArray();

        $this->assertContains($r1->id, $ids);
        $this->assertContains($r2->id, $ids);
        $this->assertNotContains($r3->id, $ids);
    }

    // -------------------------------------------------------
    // Policy view()
    // -------------------------------------------------------

    /** @test */
    public function policy_view_activo_accesible_para_todos_los_roles(): void
    {
        $r = $this->riesgo($this->activoId, $this->sectC);
        $this->assertTrue($this->policy()->view($this->nacho,  $r));
        $this->assertTrue($this->policy()->view($this->canela, $r));
        $this->assertTrue($this->policy()->view($this->lucia,  $r));
    }

    /** @test */
    public function policy_view_borrador_bloqueado_para_comite(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectA);
        $this->assertFalse($this->policy()->view($this->lucia, $r));
    }

    /** @test */
    public function policy_view_borrador_accesible_para_gerente_en_su_gerencia(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectA);
        $this->assertTrue($this->policy()->view($this->canela, $r));
    }

    /** @test */
    public function policy_view_borrador_bloqueado_para_gerente_de_otra_gerencia(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectC); // gerProd
        $this->assertFalse($this->policy()->view($this->canela, $r));
    }

    /** @test */
    public function policy_view_borrador_accesible_para_empleado_en_su_area(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectA);
        $this->assertTrue($this->policy()->view($this->nacho, $r));
    }

    /** @test */
    public function policy_view_borrador_bloqueado_para_empleado_en_sector_hermano(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectB);
        $this->assertFalse($this->policy()->view($this->nacho, $r));
    }

    /** @test */
    public function policy_view_validado_accesible_para_empleado_en_su_gerencia(): void
    {
        $r = $this->riesgo($this->validadoId, $this->sectB); // misma gerencia, distinto sector
        $this->assertTrue($this->policy()->view($this->nacho, $r));
    }

    /** @test */
    public function policy_view_validado_bloqueado_para_empleado_de_otra_gerencia(): void
    {
        $r = $this->riesgo($this->validadoId, $this->sectC);
        $this->assertFalse($this->policy()->view($this->nacho, $r));
    }

    // -------------------------------------------------------
    // HTTP: show controller
    // -------------------------------------------------------

    /** @test */
    public function show_activo_devuelve_200_para_cualquier_usuario(): void
    {
        $r = $this->riesgo($this->activoId, $this->sectC); // otra gerencia

        $this->actingAs($this->nacho)
             ->get(route('auditoria.riesgos.show', $r))
             ->assertOk();
    }

    /** @test */
    public function show_borrador_retorna_403_para_comite(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectA);

        $this->actingAs($this->lucia)
             ->get(route('auditoria.riesgos.show', $r))
             ->assertForbidden();
    }

    /** @test */
    public function show_borrador_propio_devuelve_200_para_empleado(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectA);

        $this->actingAs($this->nacho)
             ->get(route('auditoria.riesgos.show', $r))
             ->assertOk();
    }

    /** @test */
    public function show_borrador_hermano_retorna_403_para_empleado(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectB);

        $this->actingAs($this->nacho)
             ->get(route('auditoria.riesgos.show', $r))
             ->assertForbidden();
    }

    /** @test */
    public function show_borrador_otra_gerencia_retorna_403_para_gerente(): void
    {
        $r = $this->riesgo($this->borradorId, $this->sectC); // gerProd, canela es de gerAdmin

        $this->actingAs($this->canela)
             ->get(route('auditoria.riesgos.show', $r))
             ->assertForbidden();
    }

    /** @test */
    public function show_validado_otra_gerencia_retorna_403_para_empleado(): void
    {
        $r = $this->riesgo($this->validadoId, $this->sectC);

        $this->actingAs($this->nacho)
             ->get(route('auditoria.riesgos.show', $r))
             ->assertForbidden();
    }
}
