<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Área y Responsable en el alta y la edición de Control, Objetivo, PlanAccion y
 * Tarea (Concerns\OpcionesAreaResponsable), más el área obligatoria del Riesgo.
 * El área sale de la línea del usuario; el responsable, de esa área, sus
 * sub-áreas o sus ancestros hasta la gerencia. Ningún elemento nace ni queda
 * sin área.
 *
 * Árbol: Comité (Presidenta) → Gerencia (Piris) → Sistemas (Aieta) / Compras (Compradora)
 *        Comité → Contabilidad (Contador)
 */
class AltaAreaResponsableTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private Area $sistemas;

    private Area $compras;

    private Area $contabilidad;

    private User $piris;

    private User $aieta;

    private User $compradora;

    private User $contador;

    private User $presidenta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $raiz = Area::create(['nombre' => 'Comité', 'tipo' => TipoArea::Gerencia]);
        $this->gerencia = Area::create(['nombre' => 'Gerencia Propia', 'area_padre_id' => $raiz->id, 'tipo' => TipoArea::Gerencia]);
        $this->sistemas = Area::create(['nombre' => 'Coordinación Sistemas', 'area_padre_id' => $this->gerencia->id]);
        $this->compras = Area::create(['nombre' => 'Coordinación Compras', 'area_padre_id' => $this->gerencia->id]);
        $this->contabilidad = Area::create(['nombre' => 'Gerencia Contabilidad', 'area_padre_id' => $raiz->id, 'tipo' => TipoArea::Gerencia]);

        $this->presidenta = User::factory()->create(['rol' => 'gerente', 'area_id' => $raiz->id]);

        $this->piris = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->aieta = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sistemas->id]);
        $this->compradora = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->compras->id]);
        $this->contador = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->contabilidad->id]);
    }

    public static function entidades(): array
    {
        return [
            'control' => ['controles', 'controles', ['nombre' => 'Control X', 'mitigacion_default' => 5]],
            'objetivo' => ['objetivos', 'objetivos', ['nombre' => 'Objetivo X']],
            'plan' => ['planes', 'planes_accion', ['nombre' => 'Plan X']],
            'tarea' => ['tareas', 'tareas', ['nombre' => 'Tarea X', 'porcentaje_avance' => 0]],
        ];
    }

    #[Test]
    #[DataProvider('entidades')]
    public function el_alta_solo_ofrece_las_areas_de_la_linea_del_usuario(string $ruta, string $tabla, array $datos): void
    {
        $respuesta = $this->actingAs($this->aieta)->get(route("auditoria.$ruta.create"));

        $respuesta->assertOk();
        $respuesta->assertSee('Coordinación Sistemas');
        $respuesta->assertDontSee('Gerencia Contabilidad');
        $respuesta->assertDontSee('Coordinación Compras');
        $respuesta->assertDontSee('— Sin área —');
    }

    #[Test]
    #[DataProvider('entidades')]
    public function no_se_puede_crear_sin_area(string $ruta, string $tabla, array $datos): void
    {
        $this->actingAs($this->aieta)
            ->post(route("auditoria.$ruta.store"), $datos)
            ->assertSessionHasErrors('area_id');

        $this->assertDatabaseCount($tabla, 0);
    }

    #[Test]
    #[DataProvider('entidades')]
    public function no_se_puede_crear_en_un_area_de_otra_gerencia(string $ruta, string $tabla, array $datos): void
    {
        $this->actingAs($this->aieta)
            ->post(route("auditoria.$ruta.store"), $datos + ['area_id' => $this->contabilidad->id])
            ->assertForbidden();

        $this->assertDatabaseCount($tabla, 0);
    }

    #[Test]
    #[DataProvider('entidades')]
    public function el_responsable_puede_ser_de_un_area_ancestro(string $ruta, string $tabla, array $datos): void
    {
        $this->actingAs($this->aieta)
            ->post(route("auditoria.$ruta.store"), $datos + ['area_id' => $this->sistemas->id, 'user_id' => $this->piris->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas($tabla, ['area_id' => $this->sistemas->id, 'user_id' => $this->piris->id]);
    }

    #[Test]
    #[DataProvider('entidades')]
    public function el_responsable_no_puede_estar_por_encima_de_la_gerencia(string $ruta, string $tabla, array $datos): void
    {
        // Desde la coordinación y desde la gerencia misma: el Comité queda afuera.
        foreach ([$this->sistemas, $this->gerencia] as $area) {
            $this->actingAs($this->piris)
                ->post(route("auditoria.$ruta.store"), $datos + ['area_id' => $area->id, 'user_id' => $this->presidenta->id])
                ->assertSessionHasErrors('user_id');
        }

        $this->assertDatabaseCount($tabla, 0);
    }

    #[Test]
    public function la_edicion_no_ofrece_responsables_por_encima_de_la_gerencia(): void
    {
        $control = Control::factory()->create(['area_id' => $this->sistemas->id, 'user_id' => $this->aieta->id]);

        $this->actingAs($this->aieta)
            ->put(route('auditoria.controles.update', $control), [
                'nombre' => $control->nombre, 'mitigacion_default' => 5,
                'area_id' => $this->sistemas->id, 'user_id' => $this->presidenta->id,
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertEquals($this->aieta->id, $control->fresh()->user_id);
    }

    #[Test]
    #[DataProvider('entidades')]
    public function el_responsable_no_puede_ser_de_otra_gerencia(string $ruta, string $tabla, array $datos): void
    {
        $this->actingAs($this->aieta)
            ->post(route("auditoria.$ruta.store"), $datos + ['area_id' => $this->sistemas->id, 'user_id' => $this->contador->id])
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount($tabla, 0);
    }

    #[Test]
    public function el_responsable_no_puede_ser_de_una_coordinacion_hermana(): void
    {
        // Piris gestiona las dos coordinaciones, pero un control de Sistemas no
        // puede quedar a cargo de alguien de Compras: sólo línea directa.
        $this->actingAs($this->piris)
            ->post(route('auditoria.controles.store'), [
                'nombre' => 'Control X', 'mitigacion_default' => 5,
                'area_id' => $this->sistemas->id, 'user_id' => $this->compradora->id,
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertDatabaseCount('controles', 0);
    }

    #[Test]
    public function la_gerente_puede_crear_en_sus_subareas(): void
    {
        $this->actingAs($this->piris)
            ->post(route('auditoria.controles.store'), [
                'nombre' => 'Control X', 'mitigacion_default' => 5,
                'area_id' => $this->compras->id, 'user_id' => $this->compradora->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('controles', ['area_id' => $this->compras->id, 'user_id' => $this->compradora->id]);
    }

    #[Test]
    public function el_comite_puede_crear_en_cualquier_area(): void
    {
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        $this->actingAs($comite)
            ->post(route('auditoria.controles.store'), [
                'nombre' => 'Control X', 'mitigacion_default' => 5,
                'area_id' => $this->contabilidad->id, 'user_id' => $this->contador->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('controles', ['area_id' => $this->contabilidad->id]);
    }

    #[Test]
    public function no_se_puede_mover_un_elemento_a_otra_gerencia_al_editarlo(): void
    {
        $control = Control::factory()->create(['area_id' => $this->sistemas->id, 'user_id' => $this->aieta->id]);

        $this->actingAs($this->aieta)
            ->put(route('auditoria.controles.update', $control), [
                'nombre' => $control->nombre, 'mitigacion_default' => 5,
                'area_id' => $this->contabilidad->id, 'user_id' => $this->aieta->id,
            ])
            ->assertSessionHasErrors('area_id');

        $this->assertEquals($this->sistemas->id, $control->fresh()->area_id);
    }

    #[Test]
    public function no_se_puede_dejar_sin_area_un_elemento_al_editarlo(): void
    {
        $objetivo = Objetivo::create(['nombre' => 'Objetivo X', 'area_id' => $this->sistemas->id, 'user_id' => $this->aieta->id]);

        $this->actingAs($this->aieta)
            ->put(route('auditoria.objetivos.update', $objetivo), ['nombre' => 'Objetivo X', 'area_id' => ''])
            ->assertSessionHasErrors('area_id');

        $this->assertEquals($this->sistemas->id, $objetivo->fresh()->area_id);
    }

    #[Test]
    public function editar_conserva_el_responsable_actual_aunque_quede_fuera_de_la_regla(): void
    {
        // Dato previo a la regla: un plan de Sistemas a cargo de alguien de otra gerencia.
        $plan = PlanAccion::factory()->create(['area_id' => $this->sistemas->id, 'user_id' => $this->contador->id]);

        $this->actingAs($this->aieta)
            ->put(route('auditoria.planes.update', $plan), [
                'nombre' => 'Nombre nuevo', 'area_id' => $this->sistemas->id, 'user_id' => $this->contador->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals('Nombre nuevo', $plan->fresh()->nombre);
        $this->assertEquals($this->contador->id, $plan->fresh()->user_id);
    }

    #[Test]
    public function el_responsable_fuera_de_la_regla_no_se_arrastra_a_otra_area(): void
    {
        $plan = PlanAccion::factory()->create(['area_id' => $this->gerencia->id, 'user_id' => $this->contador->id]);

        $this->actingAs($this->piris)
            ->put(route('auditoria.planes.update', $plan), [
                'nombre' => $plan->nombre, 'area_id' => $this->compras->id, 'user_id' => $this->contador->id,
            ])
            ->assertSessionHasErrors('user_id');
    }

    #[Test]
    public function crear_un_plan_no_asocia_riesgos(): void
    {
        $riesgo = Riesgo::factory()->create(['area_id' => $this->sistemas->id, 'user_id' => $this->aieta->id]);

        $this->actingAs($this->aieta)
            ->post(route('auditoria.planes.store'), [
                'nombre' => 'Plan X', 'area_id' => $this->sistemas->id, 'riesgo_ids' => [$riesgo->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, PlanAccion::firstOrFail()->riesgos);
    }

    #[Test]
    public function editar_un_plan_no_toca_sus_riesgos_asociados(): void
    {
        // El form ya no manda riesgo_ids: si update() siguiera sincronizando,
        // guardar el plan le desasociaría todos los riesgos.
        $plan = PlanAccion::factory()->create(['area_id' => $this->sistemas->id, 'user_id' => $this->aieta->id]);
        $asociado = Riesgo::factory()->create(['area_id' => $this->sistemas->id, 'user_id' => $this->aieta->id]);
        $otro = Riesgo::factory()->create(['area_id' => $this->sistemas->id, 'user_id' => $this->aieta->id]);
        $plan->riesgos()->attach($asociado->id);

        $this->actingAs($this->aieta)
            ->put(route('auditoria.planes.update', $plan), [
                'nombre' => 'Nombre nuevo', 'area_id' => $this->sistemas->id, 'riesgo_ids' => [$otro->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals([$asociado->id], $plan->fresh()->riesgos->pluck('id')->all());
    }

    #[Test]
    public function crear_un_riesgo_no_asocia_objetivos(): void
    {
        $objetivo = Objetivo::create(['nombre' => 'Objetivo X', 'area_id' => $this->sistemas->id, 'user_id' => $this->aieta->id]);

        $this->actingAs($this->aieta)
            ->post(route('auditoria.riesgos.store'), $this->datosRiesgo([
                'area_id' => $this->sistemas->id, 'objetivos' => [$objetivo->id],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertCount(0, Riesgo::firstOrFail()->objetivos);
    }

    #[Test]
    public function no_se_puede_crear_un_riesgo_sin_area(): void
    {
        $this->actingAs($this->aieta)
            ->post(route('auditoria.riesgos.store'), $this->datosRiesgo())
            ->assertSessionHasErrors('area_id');

        $this->assertDatabaseCount('riesgos', 0);
    }

    private function datosRiesgo(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Riesgo de prueba',
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
            'respuesta' => 'mitigar',
        ], $overrides);
    }
}
