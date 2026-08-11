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
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un objetivo/control/plan asociado a un riesgo queda en "limbo" cuando está en
 * estado "borrado" (rechazado): nunca se muestra, para nadie, pero tampoco se
 * detacha al guardar cambios en otras asociaciones (mismo patrón que
 * GestionTareas::$ocultosIds). Por separado, uno que sigue vivo pero en
 * borrador/validado de otra gerencia tampoco se muestra a un usuario que no
 * puede verlo (axioma 1), aunque sí se preserva igual al guardar.
 */
class AsociacionesOcultasTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerAdmin;

    private Area $gerProd;

    private Area $sectC;

    private User $canela;   // gerente de la gerencia A (gerAdmin)

    private User $grassi;   // gerente de la gerencia B (gerProd)

    private User $comite;   // comité, opera sobre lo público de cualquier área

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $comiteArea = Area::create(['nombre' => 'Comité', 'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $comiteArea->id, 'tipo' => TipoArea::Gerencia]);
        $this->gerProd = Area::create(['nombre' => 'Ger. Prod', 'area_padre_id' => $comiteArea->id, 'tipo' => TipoArea::Gerencia]);
        $this->sectC = Area::create(['nombre' => 'Sector C', 'area_padre_id' => $this->gerProd->id]);

        $this->canela = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerAdmin->id]);
        $this->grassi = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerProd->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => $comiteArea->id]);
    }

    // -------------------------------------------------------
    // Controles: borrado = limbo (nunca se muestra, para nadie)
    // -------------------------------------------------------

    /** @test */
    public function un_control_borrado_no_aparece_en_la_gestion_de_controles_del_riesgo(): void
    {
        $riesgo = Riesgo::factory()->create(['area_id' => $this->sectC->id]);
        $riesgo->areas()->sync([$this->sectC->id, $this->gerProd->id]);
        $borrado = Control::factory()->create(['estado_id' => Estado::borrado()->id]);
        $riesgo->controles()->attach($borrado->id, ['mitigacion' => 5]);

        Livewire::actingAs($this->grassi)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->assertSet('seleccionados', fn ($sel) => ! collect($sel)->pluck('id')->contains($borrado->id))
            ->assertSet('ocultosIds', [$borrado->id]);
    }

    /** @test */
    public function guardar_controles_preserva_el_control_borrado_oculto_sin_detacharlo(): void
    {
        $riesgo = Riesgo::factory()->create(['area_id' => $this->sectC->id]);
        $riesgo->areas()->sync([$this->sectC->id, $this->gerProd->id]);
        $borrado = Control::factory()->create(['estado_id' => Estado::borrado()->id]);
        $vigente = Control::factory()->create(['estado_id' => Estado::aprobado()->id]);
        $riesgo->controles()->attach($borrado->id, ['mitigacion' => 5]);

        Livewire::actingAs($this->grassi)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $vigente->id)
            ->call('guardar');

        $idsPivot = $riesgo->fresh()->controles->pluck('id');
        $this->assertTrue($idsPivot->contains($borrado->id), 'El control borrado oculto no debe detacharse al guardar.');
        $this->assertTrue($idsPivot->contains($vigente->id));
    }

    // -------------------------------------------------------
    // Objetivos: visibilidad — borrador ajeno no se muestra
    // -------------------------------------------------------

    /** @test */
    public function un_objetivo_en_borrador_de_otra_gerencia_no_aparece_para_un_gerente_que_no_lo_gestiona(): void
    {
        $riesgo = Riesgo::factory()->create(['area_id' => $this->sectC->id, 'estado_id' => Estado::aprobado()->id]);
        $riesgo->areas()->sync([$this->sectC->id, $this->gerProd->id, $this->gerAdmin->id]);

        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo de B', 'estado_id' => Estado::borrador()->id,
            'area_id' => $this->sectC->id, 'user_id' => $this->grassi->id,
        ]);
        $riesgo->objetivos()->attach($objetivo->id);

        Livewire::actingAs($this->canela)
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->assertSet('seleccionados', fn ($sel) => ! collect($sel)->pluck('id')->contains($objetivo->id))
            ->assertSet('ocultosIds', [$objetivo->id]);
    }

    /** @test */
    public function el_mismo_objetivo_en_borrador_si_aparece_para_el_gerente_de_su_propia_gerencia(): void
    {
        $riesgo = Riesgo::factory()->create(['area_id' => $this->sectC->id, 'estado_id' => Estado::aprobado()->id]);
        $riesgo->areas()->sync([$this->sectC->id, $this->gerProd->id]);

        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo de B', 'estado_id' => Estado::borrador()->id,
            'area_id' => $this->sectC->id, 'user_id' => $this->grassi->id,
        ]);
        $riesgo->objetivos()->attach($objetivo->id);

        Livewire::actingAs($this->grassi)
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->assertSet('seleccionados', fn ($sel) => collect($sel)->pluck('id')->contains($objetivo->id));
    }

    /** @test */
    public function guardar_objetivos_preserva_el_borrador_ajeno_oculto_sin_detacharlo(): void
    {
        // Comité: el mismo actor del reporte original (ve lo público, no los
        // borradores ajenos) y no dispara doble validación al guardar.
        $riesgo = Riesgo::factory()->create(['area_id' => $this->sectC->id, 'estado_id' => Estado::aprobado()->id]);
        $riesgo->areas()->sync([$this->sectC->id, $this->gerProd->id]);

        $ajeno = Objetivo::create([
            'nombre' => 'Objetivo de B', 'estado_id' => Estado::borrador()->id,
            'area_id' => $this->sectC->id, 'user_id' => $this->grassi->id,
        ]);
        $publico = Objetivo::create([
            'nombre' => 'Objetivo público', 'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->sectC->id, 'user_id' => $this->grassi->id,
        ]);
        $riesgo->objetivos()->attach([$ajeno->id, $publico->id]);

        Livewire::actingAs($this->comite)
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('guardar');

        $idsPivot = $riesgo->fresh()->objetivos->pluck('id');
        $this->assertTrue($idsPivot->contains($ajeno->id), 'El borrador ajeno oculto no debe detacharse al guardar.');
        $this->assertTrue($idsPivot->contains($publico->id));
    }

    // -------------------------------------------------------
    // Planes: mismo patrón (borrado = limbo)
    // -------------------------------------------------------

    /** @test */
    public function un_plan_borrado_no_aparece_en_la_gestion_de_planes_del_riesgo(): void
    {
        $riesgo = Riesgo::factory()->create(['area_id' => $this->sectC->id]);
        $riesgo->areas()->sync([$this->sectC->id, $this->gerProd->id]);
        $borrado = PlanAccion::factory()->create(['estado_id' => Estado::borrado()->id]);
        $riesgo->planesAccion()->attach($borrado->id, ['mitigacion' => 5]);

        Livewire::actingAs($this->grassi)
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->assertSet('seleccionados', fn ($sel) => ! collect($sel)->pluck('id')->contains($borrado->id))
            ->assertSet('ocultosIds', [$borrado->id]);
    }
}
