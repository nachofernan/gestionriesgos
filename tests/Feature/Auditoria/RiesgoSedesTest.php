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
 * Sedes del riesgo (D-029): al menos una, se eligen en el alta y en el borrador,
 * y fuera de borrador se cambian desde la ficha con el circuito de propuestas.
 */
class RiesgoSedesTest extends TestCase
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

    private function sede(string $nombre): Sede
    {
        return Sede::where('nombre', $nombre)->firstOrFail();
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
            'naturalezas' => [Naturaleza::value('id')],
        ], $overrides);
    }

    private function riesgoValidado(): Riesgo
    {
        // La ficha valida todo el formulario: la respuesta es obligatoria.
        return Riesgo::factory()->validado()->create(['area_id' => $this->gerencia->id, 'respuesta' => 'mitigar']);
    }

    /** Edita la ficha dejando marcadas sólo $sedes. */
    private function proponerSedes(User $user, Riesgo $riesgo, array $sedes)
    {
        return Livewire::actingAs($user)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->set('sedes', array_map(fn (Sede $s) => (string) $s->id, $sedes))
            ->set('mensaje', 'Cambio de sedes')
            ->call('guardar');
    }

    #[Test]
    public function el_alta_exige_al_menos_una_sede(): void
    {
        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta())
            ->assertSessionHasErrors('sedes');

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta(['sedes' => []]))
            ->assertSessionHasErrors('sedes');

        $this->assertSame(0, Riesgo::count());
    }

    #[Test]
    public function el_alta_guarda_las_sedes_elegidas(): void
    {
        $necochea = $this->sede('Necochea');
        $laPlata = $this->sede('Administración Central - La Plata');

        $this->actingAs($this->gerente)
            ->post(route('auditoria.riesgos.store'), $this->datosAlta(['sedes' => [$necochea->id, $laPlata->id]]))
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$necochea->id, $laPlata->id], Riesgo::sole()->sedes->pluck('id')->all());
    }

    #[Test]
    public function el_formulario_del_borrador_cambia_las_sedes_y_lo_registra(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id, 'respuesta' => 'mitigar']);
        $necochea = $this->sede('Necochea');

        $this->actingAs($this->empleado)->put(route('auditoria.riesgos.update', $riesgo), [
            'nombre' => $riesgo->nombre,
            'tipo_riesgo_id' => $riesgo->tipo_riesgo_id,
            'respuesta' => 'mitigar',
            'area_id' => $this->gerencia->id,
            'sedes' => [$necochea->id],
            'naturalezas' => [Naturaleza::value('id')],
        ])->assertSessionHasNoErrors();

        $this->assertEquals([$necochea->id], $riesgo->fresh()->sedes->pluck('id')->all());
        $log = $riesgo->actualizaciones()->where('mensaje', 'Borrador modificado')->sole();
        $this->assertCount(4, $log->data['diff']['relaciones']['sedes']['quita']);
        $this->assertArrayNotHasKey('agrega', $log->data['diff']['relaciones']['sedes']);
    }

    #[Test]
    public function un_empleado_propone_cambiar_las_sedes_y_se_aplica_al_validar(): void
    {
        $riesgo = $this->riesgoValidado();
        $necochea = $this->sede('Necochea');

        $this->proponerSedes($this->empleado, $riesgo, [$necochea])->assertHasNoErrors();

        $this->assertCount(5, $riesgo->fresh()->sedes);
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('sedes')->sole();
        $this->assertSame('borrador', $propuesta->estado->nombre);
        $this->assertCount(4, $propuesta->data['diff']['relaciones']['sedes']['quita']);

        Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $propuesta->id);

        $this->assertEquals([$necochea->id], $riesgo->fresh()->sedes->pluck('id')->all());
    }

    #[Test]
    public function un_gerente_cambia_las_sedes_de_un_riesgo_validado_en_el_acto(): void
    {
        $riesgo = $this->riesgoValidado();
        $necochea = $this->sede('Necochea');

        $this->proponerSedes($this->gerente, $riesgo, [$necochea])->assertHasNoErrors();

        $this->assertEquals([$necochea->id], $riesgo->fresh()->sedes->pluck('id')->all());
        $this->assertNotNull($riesgo->actualizaciones()->sole()->data['activated_by'] ?? null);
    }

    #[Test]
    public function la_ficha_no_deja_un_riesgo_sin_sedes(): void
    {
        $riesgo = $this->riesgoValidado();

        $this->proponerSedes($this->gerente, $riesgo, [])->assertHasErrors('sedes');

        $this->assertCount(5, $riesgo->fresh()->sedes);
        $this->assertSame(0, $riesgo->actualizaciones()->count());
    }

    #[Test]
    public function con_una_propuesta_de_sedes_pendiente_no_se_vuelven_a_proponer(): void
    {
        $riesgo = $this->riesgoValidado();
        $this->proponerSedes($this->empleado, $riesgo, [$this->sede('Necochea')]);

        $this->proponerSedes($this->empleado, $riesgo, [$this->sede('Mar de Ajó')])->assertHasErrors('form');

        $this->assertCount(1, $riesgo->actualizaciones()->propuestasPendientes('sedes')->get());
    }

    #[Test]
    public function un_gerente_de_otra_gerencia_no_puede_proponer_sedes(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id]);
        $otraGerencia = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $otraGerencia->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->assertForbidden();
    }
}
