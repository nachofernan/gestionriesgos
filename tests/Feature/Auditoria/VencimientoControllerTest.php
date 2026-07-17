<?php

namespace Tests\Feature\Auditoria;

use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre la pantalla de Vencimientos: en qué tramo cae cada tarea según su fecha
 * y qué queda excluido del listado (terminadas al 100%, borradores, y lo que la
 * visibilidad por área ya le esconde al usuario).
 */
class VencimientoControllerTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerAdmin;

    private Area $gerProd;

    private User $canela;  // gerente gerAdmin

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $comite = Area::create(['nombre' => 'Comité', 'area_padre_id' => null]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $comite->id]);
        $this->gerProd = Area::create(['nombre' => 'Ger. Prod', 'area_padre_id' => $comite->id]);

        $this->canela = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerAdmin->id]);
    }

    private function crearTarea(array $attrs = []): Tarea
    {
        return Tarea::factory()->create(array_merge([
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerAdmin->id,
            'porcentaje_avance' => 20,
        ], $attrs));
    }

    /** @test */
    public function las_tareas_se_agrupan_por_tramo_segun_su_fecha_de_vencimiento(): void
    {
        $vencida = $this->crearTarea(['nombre' => 'Tarea vencida', 'fecha' => today()->subDays(5)]);
        $porVencer = $this->crearTarea(['nombre' => 'Tarea por vencer', 'fecha' => today()->addDays(10)]);
        $enPlazo = $this->crearTarea(['nombre' => 'Tarea en plazo', 'fecha' => today()->addDays(90)]);

        $response = $this->actingAs($this->canela)->get(route('auditoria.vencimientos.index'));

        $response->assertOk();
        $response->assertViewHas('vencidas', fn ($items) => $items->pluck('id')->all() === [$vencida->id]);
        $response->assertViewHas('porVencer', fn ($items) => $items->pluck('id')->all() === [$porVencer->id]);
        $response->assertViewHas('enPlazo', fn ($items) => $items->pluck('id')->all() === [$enPlazo->id]);
    }

    /** @test */
    public function una_tarea_que_vence_hoy_cuenta_como_por_vencer_y_no_como_vencida(): void
    {
        $hoy = $this->crearTarea(['fecha' => today()]);

        $response = $this->actingAs($this->canela)->get(route('auditoria.vencimientos.index'));

        $response->assertViewHas('vencidas', fn ($items) => $items->isEmpty());
        $response->assertViewHas('porVencer', fn ($items) => $items->pluck('id')->all() === [$hoy->id]);
    }

    /** @test */
    public function el_limite_de_treinta_dias_separa_por_vencer_de_en_plazo(): void
    {
        $borde = $this->crearTarea(['fecha' => today()->addDays(30)]);
        $pasado = $this->crearTarea(['fecha' => today()->addDays(31)]);

        $response = $this->actingAs($this->canela)->get(route('auditoria.vencimientos.index'));

        $response->assertViewHas('porVencer', fn ($items) => $items->pluck('id')->all() === [$borde->id]);
        $response->assertViewHas('enPlazo', fn ($items) => $items->pluck('id')->all() === [$pasado->id]);
    }

    /** @test */
    public function las_vencidas_se_ordenan_de_la_mas_atrasada_a_la_menos(): void
    {
        $reciente = $this->crearTarea(['fecha' => today()->subDays(2)]);
        $antigua = $this->crearTarea(['fecha' => today()->subDays(60)]);

        $response = $this->actingAs($this->canela)->get(route('auditoria.vencimientos.index'));

        $response->assertViewHas('vencidas', fn ($items) => $items->pluck('id')->all() === [$antigua->id, $reciente->id]);
    }

    /** @test */
    public function una_tarea_terminada_al_cien_por_ciento_no_figura_aunque_este_vencida(): void
    {
        $this->crearTarea(['nombre' => 'Tarea terminada', 'fecha' => today()->subDays(5), 'porcentaje_avance' => 100]);

        $response = $this->actingAs($this->canela)->get(route('auditoria.vencimientos.index'));

        $response->assertViewHas('vencidas', fn ($items) => $items->isEmpty());
        $response->assertDontSee('Tarea terminada');
    }

    /** @test */
    public function una_tarea_en_borrador_no_figura_porque_todavia_no_es_un_compromiso(): void
    {
        $this->crearTarea([
            'nombre' => 'Tarea borrador',
            'fecha' => today()->subDays(5),
            'estado_id' => Estado::borrador()->id,
        ]);

        $response = $this->actingAs($this->canela)->get(route('auditoria.vencimientos.index'));

        $response->assertViewHas('vencidas', fn ($items) => $items->isEmpty());
    }

    /** @test */
    public function las_tareas_sin_fecha_se_listan_aparte_y_no_como_vencidas(): void
    {
        $sinFecha = $this->crearTarea(['nombre' => 'Tarea sin fecha', 'fecha' => null]);

        $response = $this->actingAs($this->canela)->get(route('auditoria.vencimientos.index'));

        $response->assertViewHas('vencidas', fn ($items) => $items->isEmpty());
        $response->assertViewHas('sinFecha', fn ($items) => $items->pluck('id')->all() === [$sinFecha->id]);
        $response->assertSee('Sin vencimiento definido');
    }

    /** @test */
    public function la_fila_de_la_tarea_muestra_el_plan_al_que_pertenece(): void
    {
        $tarea = $this->crearTarea(['fecha' => today()->subDays(3)]);
        $plan = PlanAccion::create([
            'codigo' => 'PA-001',
            'nombre' => 'Plan de contingencia',
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerAdmin->id,
        ]);
        $plan->tareas()->attach($tarea);

        $this->actingAs($this->canela)
            ->get(route('auditoria.vencimientos.index'))
            ->assertSee('Plan de contingencia');
    }

    /** @test */
    public function un_borrador_de_otra_gerencia_no_se_filtra_en_el_listado(): void
    {
        $this->crearTarea([
            'nombre' => 'Borrador ajeno',
            'fecha' => today()->subDays(5),
            'area_id' => $this->gerProd->id,
            'estado_id' => Estado::borrador()->id,
        ]);

        $this->actingAs($this->canela)
            ->get(route('auditoria.vencimientos.index'))
            ->assertDontSee('Borrador ajeno');
    }
}
