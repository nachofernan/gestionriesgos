<?php

namespace Tests\Feature\Auditoria;

use App\Models\Auditoria\Area;
use App\Models\Auditoria\Naturaleza;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Sede;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Database\Seeders\NaturalezaSeeder;
use Database\Seeders\SedeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Al asignar el área de un Riesgo, el usuario sólo puede elegir la propia y sus
 * descendientes: nunca hermanas ni primas. Ver User::idsAreasGestionables() y
 * RiesgoController::create/store/edit/update.
 */
class RiesgoAreaLineaTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private Area $subarea;

    private Area $hermana;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([EstadoRiesgoSeeder::class, SedeSeeder::class, NaturalezaSeeder::class]);

        $raiz = Area::create(['nombre' => 'Comité']);
        $this->gerencia = Area::create(['nombre' => 'Gerencia Propia', 'area_padre_id' => $raiz->id]);
        $this->subarea = Area::create(['nombre' => 'Sub-área Propia', 'area_padre_id' => $this->gerencia->id]);
        $this->hermana = Area::create(['nombre' => 'Gerencia Hermana', 'area_padre_id' => $raiz->id]);

        $this->usuario = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
    }

    private function datosWizard(array $overrides = []): array
    {
        return array_merge([
            'sedes' => Sede::pluck('id')->all(),
            'naturalezas' => [Naturaleza::value('id')],
            'nombre' => 'Riesgo de prueba',
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
            'respuesta' => 'mitigar',
        ], $overrides);
    }

    #[Test]
    public function el_select_de_area_solo_ofrece_el_area_propia_y_sus_subareas(): void
    {
        $respuesta = $this->actingAs($this->usuario)->get(route('auditoria.riesgos.create'));

        $respuesta->assertStatus(200);
        $respuesta->assertSee('Gerencia Propia');
        $respuesta->assertSee('Sub-área Propia');
        $respuesta->assertDontSee('Gerencia Hermana');
    }

    #[Test]
    public function se_puede_crear_un_riesgo_en_una_subarea_propia(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard(['area_id' => $this->subarea->id]));

        $respuesta->assertSessionHasNoErrors();
        $this->assertEquals($this->subarea->id, Riesgo::firstOrFail()->area_id);
    }

    #[Test]
    public function no_se_puede_crear_un_riesgo_en_un_area_hermana(): void
    {
        // Sólo alcanzable manipulando el form: el select ya no ofrece el área.
        // Lo corta authorize('create') en el store, antes de validar.
        $respuesta = $this->actingAs($this->usuario)
            ->post(route('auditoria.riesgos.store'), $this->datosWizard(['area_id' => $this->hermana->id]));

        $respuesta->assertForbidden();
        $this->assertDatabaseCount('riesgos', 0);
    }

    #[Test]
    public function no_se_puede_mover_un_riesgo_a_un_area_hermana_al_editarlo(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $this->usuario->id,
            'area_id' => $this->gerencia->id,
        ]);

        $respuesta = $this->actingAs($this->usuario)->put(route('auditoria.riesgos.update', $riesgo), [
            'sedes' => Sede::pluck('id')->all(),
            'naturalezas' => [Naturaleza::value('id')],
            'nombre' => $riesgo->nombre,
            'tipo_riesgo_id' => $riesgo->tipo_riesgo_id,
            'area_id' => $this->hermana->id,
        ]);

        $respuesta->assertSessionHasErrors('area_id');
        $this->assertEquals($this->gerencia->id, $riesgo->fresh()->area_id);
    }
}
