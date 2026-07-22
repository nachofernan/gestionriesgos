<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\PlanAccion\Show\GestionTareas;
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
use Tests\TestCase;

/**
 * Núcleo sagrado (Axioma 1): los componentes Livewire de gestión de relaciones
 * mutan datos y por lo tanto deben llamar authorize() antes de sincronizar. Un
 * gerente de otra gerencia (que ve la entidad porque está validada/aprobada, o
 * sea pública) no debe poder mutar sus relaciones: guardar()/guardarNuevaTarea()
 * tienen que devolver 403. El gerente que sí gestiona la entidad no recibe 403.
 */
class GestionRelacionesAutorizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $gerenteProd;   // gestiona el riesgo/plan de producción

    private User $gerenteAdmin;  // gerente ajeno: NO gestiona producción

    private Area $gerProd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $gerProd = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $gerAdmin = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);

        $this->gerenteProd = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerProd->id]);
        $this->gerenteAdmin = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerAdmin->id]);

        $this->gerProd = $gerProd;
    }

    private function riesgoValidadoProd(): Riesgo
    {
        $riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $this->gerProd->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);

        return $riesgo->load(['areas', 'estado']);
    }

    private function planValidadoProd(): PlanAccion
    {
        return PlanAccion::factory()->create([
            'area_id' => $this->gerProd->id,
            'estado_id' => Estado::validado()->id,
        ])->load('estado');
    }

    private function objetivoProd(): Objetivo
    {
        return Objetivo::create([
            'nombre' => 'Objetivo Producción',
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerProd->id,
            'user_id' => $this->gerenteProd->id,
        ]);
    }

    // -------------------------------------------------------
    // GestionTareas (mutación sobre el PlanAccion)
    // -------------------------------------------------------

    /** @test */
    public function gestion_tareas_guardar_devuelve_403_para_gerente_de_otra_gerencia(): void
    {
        $plan = $this->planValidadoProd();

        Livewire::actingAs($this->gerenteAdmin)
            ->test(GestionTareas::class, ['plan' => $plan])
            ->call('guardar')
            ->assertForbidden();
    }

    /** @test */
    public function gestion_tareas_guardar_nueva_tarea_devuelve_403_para_gerente_de_otra_gerencia(): void
    {
        $plan = $this->planValidadoProd();

        Livewire::actingAs($this->gerenteAdmin)
            ->test(GestionTareas::class, ['plan' => $plan])
            ->set('nuevaNombre', 'Tarea intrusa')
            ->call('guardarNuevaTarea')
            ->assertForbidden();
    }

    /** @test */
    public function gestion_tareas_guardar_no_devuelve_403_para_gerente_que_gestiona(): void
    {
        $plan = $this->planValidadoProd();

        Livewire::actingAs($this->gerenteProd)
            ->test(GestionTareas::class, ['plan' => $plan])
            ->call('guardar')
            ->assertOk();
    }

    // -------------------------------------------------------
    // GestionControles (mutación sobre el Riesgo)
    // -------------------------------------------------------

    /** @test */
    public function gestion_controles_guardar_devuelve_403_para_gerente_de_otra_gerencia(): void
    {
        $riesgo = $this->riesgoValidadoProd();

        Livewire::actingAs($this->gerenteAdmin)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('guardar')
            ->assertForbidden();
    }

    /** @test */
    public function gestion_controles_guardar_no_devuelve_403_para_gerente_que_gestiona(): void
    {
        $riesgo = $this->riesgoValidadoProd();

        Livewire::actingAs($this->gerenteProd)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('guardar')
            ->assertOk();
    }

    // -------------------------------------------------------
    // GestionPlanes (mutación sobre el Riesgo)
    // -------------------------------------------------------

    /** @test */
    public function gestion_planes_guardar_devuelve_403_para_gerente_de_otra_gerencia(): void
    {
        $riesgo = $this->riesgoValidadoProd();

        Livewire::actingAs($this->gerenteAdmin)
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('guardar')
            ->assertForbidden();
    }

    /** @test */
    public function gestion_planes_guardar_no_devuelve_403_para_gerente_que_gestiona(): void
    {
        $riesgo = $this->riesgoValidadoProd();

        Livewire::actingAs($this->gerenteProd)
            ->test(GestionPlanes::class, ['riesgo' => $riesgo])
            ->call('guardar')
            ->assertOk();
    }

    // -------------------------------------------------------
    // GestionObjetivos (mutación sobre el Riesgo)
    // -------------------------------------------------------

    /** @test */
    public function gestion_objetivos_guardar_devuelve_403_para_gerente_de_otra_gerencia(): void
    {
        $riesgo = $this->riesgoValidadoProd();
        // El riesgo debe tener al menos un objetivo asociado: si no, guardar() corta
        // antes de authorize() con el error de "al menos un objetivo".
        $riesgo->objetivos()->sync([$this->objetivoProd()->id]);

        Livewire::actingAs($this->gerenteAdmin)
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('guardar')
            ->assertForbidden();
    }

    /** @test */
    public function gestion_objetivos_guardar_no_devuelve_403_para_gerente_que_gestiona(): void
    {
        $riesgo = $this->riesgoValidadoProd();
        $riesgo->objetivos()->sync([$this->objetivoProd()->id]);

        Livewire::actingAs($this->gerenteProd)
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('guardar')
            ->assertOk();
    }

    // -------------------------------------------------------
    // Rótulo "Cambios aplicados": la asociación aplicada en el acto (gerente que
    // gestiona sobre riesgo validado) queda marcada con activated_by, que es lo
    // que el historial usa para rotularla "Cambios aplicados" y no "propuestos".
    // -------------------------------------------------------

    /** @test */
    public function una_asociacion_de_controles_aplicada_en_el_acto_queda_marcada_como_activada(): void
    {
        $riesgo = $this->riesgoValidadoProd();
        $control = Control::factory()->create([
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerProd->id,
        ]);

        Livewire::actingAs($this->gerenteProd)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $control->id)
            ->call('guardar')
            ->assertOk();

        $actualizacion = $riesgo->actualizaciones()->latest('id')->first();

        $this->assertNotNull($actualizacion);
        $this->assertSame($this->gerenteProd->name, $actualizacion->data['activated_by'] ?? null);
    }
}
