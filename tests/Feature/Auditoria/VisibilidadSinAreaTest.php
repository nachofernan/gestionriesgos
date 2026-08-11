<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use App\Policies\Auditoria\ObjetivoPolicy;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Visibilidad de entidades "sin área" (area_id null): Objetivo, Control,
 * PlanAccion y Tarea comparten HasVisibilityScope y la misma forma de Policy.
 * Regla: mientras el elemento no cruza a validado/aprobado (público para
 * todos), "sin área" NO es "visible para cualquiera" — lo gestionan el
 * responsable (user_id) y quien administra el área de ese responsable, igual
 * que si el elemento tuviera esa área. Bug original: el scope no contemplaba
 * este caso y el creador no veía su propio borrador sin área en el listado.
 *
 * Jerarquía usada:
 *   comite (raíz)
 *   ├── gerA
 *   │   ├── sectA1  ← nacho (empleado, crea el elemento sin área)
 *   │   └── sectA2  ← tito  (empleado, hermano de nacho)
 *   └── gerB         ← grassi (gerente, sin relación con nacho)
 */
class VisibilidadSinAreaTest extends TestCase
{
    use RefreshDatabase;

    private Area $comite;

    private Area $gerA;

    private Area $gerB;

    private Area $sectA1;

    private Area $sectA2;

    private User $lucia;   // comité

    private User $canela;  // gerente – gerA (ancestro de nacho)

    private User $nacho;   // empleado – sectA1 (crea el elemento sin área)

    private User $tito;    // empleado – sectA2 (hermano de nacho)

    private User $grassi;  // gerente – gerB (sin relación con nacho)

    private int $borradorId;

    private int $validadoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EstadoRiesgoSeeder::class);

        $this->comite = Area::create(['nombre' => 'Comité', 'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]);
        $this->gerA = Area::create(['nombre' => 'Ger. A', 'area_padre_id' => $this->comite->id, 'tipo' => TipoArea::Gerencia]);
        $this->gerB = Area::create(['nombre' => 'Ger. B', 'area_padre_id' => $this->comite->id, 'tipo' => TipoArea::Gerencia]);
        $this->sectA1 = Area::create(['nombre' => 'Sector A1', 'area_padre_id' => $this->gerA->id]);
        $this->sectA2 = Area::create(['nombre' => 'Sector A2', 'area_padre_id' => $this->gerA->id]);

        $this->lucia = User::factory()->create(['rol' => 'comite', 'area_id' => $this->comite->id]);
        $this->canela = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerA->id]);
        $this->nacho = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectA1->id]);
        $this->tito = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectA2->id]);
        $this->grassi = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerB->id]);

        $this->borradorId = Estado::borrador()->id;
        $this->validadoId = Estado::validado()->id;
    }

    private function objetivo(int $estadoId, User $responsable): Objetivo
    {
        return Objetivo::create([
            'nombre' => 'Objetivo sin área',
            'estado_id' => $estadoId,
            'area_id' => null,
            'user_id' => $responsable->id,
        ]);
    }

    private function policy(): ObjetivoPolicy
    {
        return new ObjetivoPolicy;
    }

    // -------------------------------------------------------
    // Caso reportado: el creador ve su propio borrador sin área
    // -------------------------------------------------------

    /** @test */
    public function creador_ve_en_el_listado_su_borrador_sin_area(): void
    {
        $o = $this->objetivo($this->borradorId, $this->nacho);

        $ids = Objetivo::visiblePara($this->nacho)->pluck('id')->toArray();
        $this->assertContains($o->id, $ids);
    }

    /** @test */
    public function gerente_del_responsable_ve_y_gestiona_el_borrador_sin_area(): void
    {
        // canela es gerente de gerA, ancestro de sectA1 donde está nacho
        $o = $this->objetivo($this->borradorId, $this->nacho);

        $ids = Objetivo::visiblePara($this->canela)->pluck('id')->toArray();
        $this->assertContains($o->id, $ids);
        $this->assertTrue($this->policy()->update($this->canela, $o));
    }

    /** @test */
    public function gerente_sin_relacion_con_el_responsable_no_ve_ni_gestiona_el_borrador_sin_area(): void
    {
        // grassi (gerB) no tiene ninguna relación de jerarquía con nacho (sectA1, gerA)
        $o = $this->objetivo($this->borradorId, $this->nacho);

        $ids = Objetivo::visiblePara($this->grassi)->pluck('id')->toArray();
        $this->assertNotContains($o->id, $ids);
        $this->assertFalse($this->policy()->view($this->grassi, $o));
        $this->assertFalse($this->policy()->update($this->grassi, $o));
    }

    /** @test */
    public function comite_no_ve_borrador_sin_area(): void
    {
        $o = $this->objetivo($this->borradorId, $this->nacho);

        $ids = Objetivo::visiblePara($this->lucia)->pluck('id')->toArray();
        $this->assertNotContains($o->id, $ids);
        $this->assertFalse($this->policy()->view($this->lucia, $o));
    }

    /** @test */
    public function validado_sin_area_es_publico_para_todos(): void
    {
        $o = $this->objetivo($this->validadoId, $this->nacho);

        $this->assertContains($o->id, Objetivo::visiblePara($this->grassi)->pluck('id')->toArray());
        $this->assertContains($o->id, Objetivo::visiblePara($this->lucia)->pluck('id')->toArray());
    }

    // -------------------------------------------------------
    // Regresión: mismo patrón en Control, PlanAccion y Tarea
    // -------------------------------------------------------

    /** @test */
    public function control_sin_area_sigue_el_mismo_patron(): void
    {
        $c = Control::create([
            'nombre' => 'C sin área',
            'mitigacion_default' => 5,
            'estado_id' => $this->borradorId,
            'area_id' => null,
            'user_id' => $this->nacho->id,
        ]);

        $this->assertContains($c->id, Control::visiblePara($this->nacho)->pluck('id')->toArray());
        $this->assertContains($c->id, Control::visiblePara($this->canela)->pluck('id')->toArray());
        $this->assertNotContains($c->id, Control::visiblePara($this->grassi)->pluck('id')->toArray());
    }

    /** @test */
    public function plan_accion_sin_area_sigue_el_mismo_patron(): void
    {
        $p = PlanAccion::create([
            'codigo' => 'PA-TEST',
            'nombre' => 'Plan sin área',
            'estado_id' => $this->borradorId,
            'area_id' => null,
            'user_id' => $this->nacho->id,
        ]);

        $this->assertContains($p->id, PlanAccion::visiblePara($this->nacho)->pluck('id')->toArray());
        $this->assertContains($p->id, PlanAccion::visiblePara($this->canela)->pluck('id')->toArray());
        $this->assertNotContains($p->id, PlanAccion::visiblePara($this->grassi)->pluck('id')->toArray());
    }

    /** @test */
    public function tarea_sin_area_sigue_el_mismo_patron(): void
    {
        $t = Tarea::create([
            'nombre' => 'Tarea sin área',
            'estado_id' => $this->borradorId,
            'area_id' => null,
            'user_id' => $this->nacho->id,
        ]);

        $this->assertContains($t->id, Tarea::visiblePara($this->nacho)->pluck('id')->toArray());
        $this->assertContains($t->id, Tarea::visiblePara($this->canela)->pluck('id')->toArray());
        $this->assertNotContains($t->id, Tarea::visiblePara($this->grassi)->pluck('id')->toArray());
    }
}
