<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Riesgo\Show\FichaRiesgo;
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
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Naturalezas del riesgo (D-032): al menos una; con dos o más, fundamento
 * obligatorio. Se eligen en el alta y en el borrador, y fuera de borrador se
 * proponen desde la ficha junto con su fundamento, en una sola propuesta.
 */
class RiesgoNaturalezasTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private User $empleado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([EstadoRiesgoSeeder::class, SedeSeeder::class, NaturalezaSeeder::class]);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
    }

    private function naturaleza(string $nombre): Naturaleza
    {
        return Naturaleza::where('nombre', $nombre)->firstOrFail();
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

    private function proponerNaturalezas(User $user, Riesgo $riesgo, array $naturalezas, ?string $fundamento = null)
    {
        return Livewire::actingAs($user)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->set('naturalezas', array_map(fn (Naturaleza $n) => (string) $n->id, $naturalezas))
            ->set('fundamentoNaturaleza', $fundamento)
            ->set('mensaje', 'Cambio de naturaleza')
            ->call('guardar');
    }

    #[Test]
    public function el_alta_exige_al_menos_una_naturaleza(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta())
            ->assertSessionHasErrors('naturalezas');

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta(['naturalezas' => []]))
            ->assertSessionHasErrors('naturalezas');

        $this->assertSame(0, Riesgo::count());
    }

    #[Test]
    public function con_una_naturaleza_no_hace_falta_fundamento_y_no_se_guarda(): void
    {
        $medioambiente = $this->naturaleza('Medioambiente');

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta([
                'naturalezas' => [$medioambiente->id],
                'fundamento_naturaleza' => 'Texto que no aplica',
            ]))
            ->assertSessionHasNoErrors();

        $riesgo = Riesgo::sole();
        $this->assertEquals([$medioambiente->id], $riesgo->naturalezas->pluck('id')->all());
        $this->assertNull($riesgo->fundamento_naturaleza);
    }

    #[Test]
    public function con_dos_naturalezas_el_fundamento_es_obligatorio(): void
    {
        $ids = [$this->naturaleza('Medioambiente')->id, $this->naturaleza('Gobernanza')->id];

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta(['naturalezas' => $ids]))
            ->assertSessionHasErrors('fundamento_naturaleza');
        $this->assertSame(0, Riesgo::count());

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta([
                'naturalezas' => $ids,
                'fundamento_naturaleza' => 'Afecta el ambiente y la gestión.',
            ]))
            ->assertSessionHasNoErrors();

        $riesgo = Riesgo::sole();
        $this->assertEqualsCanonicalizing($ids, $riesgo->naturalezas->pluck('id')->all());
        $this->assertSame('Afecta el ambiente y la gestión.', $riesgo->fundamento_naturaleza);
    }

    #[Test]
    public function el_formulario_del_borrador_cambia_las_naturalezas_y_lo_registra(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id, 'respuesta' => 'mitigar']);
        $anticorrupcion = $this->naturaleza('Anticorrupción');

        $this->actingAs($this->empleado)->put(route('auditoria.riesgos.update', $riesgo), [
            'nombre' => $riesgo->nombre,
            'tipo_riesgo_id' => $riesgo->tipo_riesgo_id,
            'respuesta' => 'mitigar',
            'area_id' => $this->gerencia->id,
            'sedes' => Sede::pluck('id')->all(),
            'naturalezas' => [$anticorrupcion->id],
        ])->assertSessionHasNoErrors();

        $this->assertEquals([$anticorrupcion->id], $riesgo->fresh()->naturalezas->pluck('id')->all());
        $log = $riesgo->actualizaciones()->where('mensaje', 'Borrador modificado')->sole();
        $this->assertSame('Anticorrupción', $log->data['diff']['relaciones']['naturalezas']['agrega'][0]['nombre']);
    }

    #[Test]
    public function un_empleado_propone_naturalezas_con_fundamento_y_se_aplican_juntos_al_validar(): void
    {
        $riesgo = $this->riesgoValidado();
        $antes = $riesgo->naturalezas->pluck('id')->all();
        $nuevas = [$this->naturaleza('Medioambiente'), $this->naturaleza('Anticorrupción')];

        $this->proponerNaturalezas($this->empleado, $riesgo, $nuevas, 'Porque sí, y está fundamentado.')->assertHasNoErrors();

        $riesgo->refresh();
        $this->assertEquals($antes, $riesgo->naturalezas->pluck('id')->all());
        $this->assertNull($riesgo->fundamento_naturaleza);

        // Una sola propuesta con las dos cosas: no se puede validar una sin la otra.
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('naturalezas')->sole();
        $this->assertSame('Porque sí, y está fundamentado.', $propuesta->data['campos']['fundamento_naturaleza']);

        Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $propuesta->id);

        $riesgo->refresh();
        $this->assertEqualsCanonicalizing(array_map(fn ($n) => $n->id, $nuevas), $riesgo->naturalezas->pluck('id')->all());
        $this->assertSame('Porque sí, y está fundamentado.', $riesgo->fundamento_naturaleza);
    }

    #[Test]
    public function la_ficha_exige_fundamento_con_dos_naturalezas(): void
    {
        $riesgo = $this->riesgoValidado();

        $this->proponerNaturalezas($this->gerente, $riesgo, [$this->naturaleza('Medioambiente'), $this->naturaleza('Gobernanza')])
            ->assertHasErrors('fundamentoNaturaleza');

        $this->assertSame(0, $riesgo->actualizaciones()->count());
    }

    #[Test]
    public function la_ficha_no_deja_un_riesgo_sin_naturaleza(): void
    {
        $riesgo = $this->riesgoValidado();

        $this->proponerNaturalezas($this->gerente, $riesgo, [])->assertHasErrors('naturalezas');

        $this->assertCount(1, $riesgo->fresh()->naturalezas);
        $this->assertSame(0, $riesgo->actualizaciones()->count());
    }

    #[Test]
    public function volver_a_una_naturaleza_borra_el_fundamento(): void
    {
        $riesgo = $this->riesgoValidado();
        $medioambiente = $this->naturaleza('Medioambiente');
        $riesgo->naturalezas()->sync([$medioambiente->id, $this->naturaleza('Gobernanza')->id]);
        $riesgo->update(['fundamento_naturaleza' => 'Viejo fundamento']);

        $this->proponerNaturalezas($this->gerente, $riesgo, [$medioambiente], 'Viejo fundamento')->assertHasNoErrors();

        $riesgo->refresh();
        $this->assertEquals([$medioambiente->id], $riesgo->naturalezas->pluck('id')->all());
        $this->assertNull($riesgo->fundamento_naturaleza);
    }

    #[Test]
    public function con_una_propuesta_de_naturalezas_pendiente_no_se_vuelven_a_proponer(): void
    {
        $riesgo = $this->riesgoValidado();
        $this->proponerNaturalezas($this->empleado, $riesgo, [$this->naturaleza('Medioambiente')]);

        $this->proponerNaturalezas($this->empleado, $riesgo, [$this->naturaleza('Anticorrupción')])->assertHasErrors('form');

        $this->assertCount(1, $riesgo->actualizaciones()->propuestasPendientes('naturalezas')->get());
    }

    #[Test]
    public function un_gerente_de_otra_gerencia_no_puede_proponer_naturalezas(): void
    {
        $riesgo = $this->riesgoValidado();
        $otraGerencia = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $otraGerencia->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->assertForbidden();

        $this->actingAs($ajeno)->put(route('auditoria.riesgos.update', Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id])), [
            'naturalezas' => [$this->naturaleza('Medioambiente')->id],
        ])->assertForbidden();
    }
}
