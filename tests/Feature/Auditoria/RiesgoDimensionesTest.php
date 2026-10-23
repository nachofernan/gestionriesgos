<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Riesgo\Show\FichaRiesgo;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Dimension;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Sede;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\DimensionSeeder;
use Database\Seeders\EstadoRiesgoSeeder;
use Database\Seeders\SedeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dimensiones del riesgo (D-032): al menos una; con dos o más, fundamento
 * obligatorio. Se eligen en el alta y en el borrador, y fuera de borrador se
 * proponen desde la ficha junto con su fundamento, en una sola propuesta.
 */
class RiesgoDimensionesTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private User $empleado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([EstadoRiesgoSeeder::class, SedeSeeder::class, DimensionSeeder::class]);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
    }

    private function dimension(string $nombre): Dimension
    {
        return Dimension::where('nombre', $nombre)->firstOrFail();
    }

    private function datosAlta(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Riesgo de prueba',
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
            'respuesta' => 'mitigar',
            'area_id' => $this->gerencia->id,
            'sedes' => Sede::pluck('id')->all(),
        ], $overrides);
    }

    private function riesgoValidado(): Riesgo
    {
        return Riesgo::factory()->validado()->create(['area_id' => $this->gerencia->id, 'respuesta' => 'mitigar']);
    }

    private function proponerDimensiones(User $user, Riesgo $riesgo, array $dimensiones, ?string $fundamento = null)
    {
        return Livewire::actingAs($user)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->set('dimensiones', array_map(fn (Dimension $n) => (string) $n->id, $dimensiones))
            ->set('fundamentoDimension', $fundamento)
            ->set('mensaje', 'Cambio de dimensión')
            ->call('guardar');
    }

    #[Test]
    public function el_alta_exige_al_menos_una_dimension(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta())
            ->assertSessionHasErrors('dimensiones');

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta(['dimensiones' => []]))
            ->assertSessionHasErrors('dimensiones');

        $this->assertSame(0, Riesgo::count());
    }

    #[Test]
    public function con_una_dimension_no_hace_falta_fundamento_y_no_se_guarda(): void
    {
        $medioambiente = $this->dimension('Medioambiente');

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta([
                'dimensiones' => [$medioambiente->id],
                'fundamento_dimension' => 'Texto que no aplica',
            ]))
            ->assertSessionHasNoErrors();

        $riesgo = Riesgo::sole();
        $this->assertEquals([$medioambiente->id], $riesgo->dimensiones->pluck('id')->all());
        $this->assertNull($riesgo->fundamento_dimension);
    }

    #[Test]
    public function con_dos_dimensiones_el_fundamento_es_obligatorio(): void
    {
        $ids = [$this->dimension('Medioambiente')->id, $this->dimension('Gobernanza')->id];

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta(['dimensiones' => $ids]))
            ->assertSessionHasErrors('fundamento_dimension');
        $this->assertSame(0, Riesgo::count());

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta([
                'dimensiones' => $ids,
                'fundamento_dimension' => 'Afecta el ambiente y la gestión.',
            ]))
            ->assertSessionHasNoErrors();

        $riesgo = Riesgo::sole();
        $this->assertEqualsCanonicalizing($ids, $riesgo->dimensiones->pluck('id')->all());
        $this->assertSame('Afecta el ambiente y la gestión.', $riesgo->fundamento_dimension);
    }

    #[Test]
    public function el_formulario_del_borrador_cambia_las_dimensiones_y_lo_registra(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id, 'respuesta' => 'mitigar']);
        $anticorrupcion = $this->dimension('Anticorrupción');

        $this->actingAs($this->empleado)->put(route('auditoria.riesgos.update', $riesgo), [
            'nombre' => $riesgo->nombre,
            'tipo_riesgo_id' => $riesgo->tipo_riesgo_id,
            'respuesta' => 'mitigar',
            'area_id' => $this->gerencia->id,
            'sedes' => Sede::pluck('id')->all(),
            'dimensiones' => [$anticorrupcion->id],
        ])->assertSessionHasNoErrors();

        $this->assertEquals([$anticorrupcion->id], $riesgo->fresh()->dimensiones->pluck('id')->all());
        $log = $riesgo->actualizaciones()->where('mensaje', 'Borrador modificado')->sole();
        $this->assertSame('Anticorrupción', $log->data['diff']['relaciones']['dimensiones']['agrega'][0]['nombre']);
    }

    #[Test]
    public function un_empleado_propone_dimensiones_con_fundamento_y_se_aplican_juntos_al_validar(): void
    {
        $riesgo = $this->riesgoValidado();
        $antes = $riesgo->dimensiones->pluck('id')->all();
        $nuevas = [$this->dimension('Medioambiente'), $this->dimension('Anticorrupción')];

        $this->proponerDimensiones($this->empleado, $riesgo, $nuevas, 'Porque sí, y está fundamentado.')->assertHasNoErrors();

        $riesgo->refresh();
        $this->assertEquals($antes, $riesgo->dimensiones->pluck('id')->all());
        $this->assertNull($riesgo->fundamento_dimension);

        // Una sola propuesta con las dos cosas: no se puede validar una sin la otra.
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('dimensiones')->sole();
        $this->assertSame('Porque sí, y está fundamentado.', $propuesta->data['campos']['fundamento_dimension']);

        Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $propuesta->id);

        $riesgo->refresh();
        $this->assertEqualsCanonicalizing(array_map(fn ($n) => $n->id, $nuevas), $riesgo->dimensiones->pluck('id')->all());
        $this->assertSame('Porque sí, y está fundamentado.', $riesgo->fundamento_dimension);
    }

    #[Test]
    public function la_ficha_exige_fundamento_con_dos_dimensiones(): void
    {
        $riesgo = $this->riesgoValidado();

        $this->proponerDimensiones($this->gerente, $riesgo, [$this->dimension('Medioambiente'), $this->dimension('Gobernanza')])
            ->assertHasErrors('fundamentoDimension');

        $this->assertSame(0, $riesgo->actualizaciones()->count());
    }

    #[Test]
    public function la_ficha_no_deja_un_riesgo_sin_dimension(): void
    {
        $riesgo = $this->riesgoValidado();

        $this->proponerDimensiones($this->gerente, $riesgo, [])->assertHasErrors('dimensiones');

        $this->assertCount(1, $riesgo->fresh()->dimensiones);
        $this->assertSame(0, $riesgo->actualizaciones()->count());
    }

    #[Test]
    public function volver_a_una_dimension_borra_el_fundamento(): void
    {
        $riesgo = $this->riesgoValidado();
        $medioambiente = $this->dimension('Medioambiente');
        $riesgo->dimensiones()->sync([$medioambiente->id, $this->dimension('Gobernanza')->id]);
        $riesgo->update(['fundamento_dimension' => 'Viejo fundamento']);

        $this->proponerDimensiones($this->gerente, $riesgo, [$medioambiente], 'Viejo fundamento')->assertHasNoErrors();

        $riesgo->refresh();
        $this->assertEquals([$medioambiente->id], $riesgo->dimensiones->pluck('id')->all());
        $this->assertNull($riesgo->fundamento_dimension);
    }

    #[Test]
    public function con_una_propuesta_de_dimensiones_pendiente_no_se_vuelven_a_proponer(): void
    {
        $riesgo = $this->riesgoValidado();
        $this->proponerDimensiones($this->empleado, $riesgo, [$this->dimension('Medioambiente')]);

        $this->proponerDimensiones($this->empleado, $riesgo, [$this->dimension('Anticorrupción')])->assertHasErrors('form');

        $this->assertCount(1, $riesgo->actualizaciones()->propuestasPendientes('dimensiones')->get());
    }

    #[Test]
    public function un_gerente_de_otra_gerencia_no_puede_proponer_dimensiones(): void
    {
        $riesgo = $this->riesgoValidado();
        $otraGerencia = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $otraGerencia->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->assertForbidden();

        $this->actingAs($ajeno)->put(route('auditoria.riesgos.update', Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id])), [
            'dimensiones' => [$this->dimension('Medioambiente')->id],
        ])->assertForbidden();
    }
}
