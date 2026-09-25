<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\Conversacion;
use App\Livewire\Auditoria\Objetivo\Show\FichaObjetivo;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rediseño de objetivo/show: la Ficha se edita en el bloque (nombre, descripción,
 * fecha objetivo) y registra sólo lo que cambió, como propuesta o aplicado según
 * el rol y el estado. Fecha objetivo sin tocar no genera un cambio fantasma.
 */
class FichaObjetivoTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $empleado;

    private User $gerente;

    private Objetivo $objetivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->objetivo = Objetivo::create([
            'nombre' => 'Reducir reclamos',
            'descripcion' => 'Bajar un 20%',
            'fecha_objetivo' => '2026-12-31',
            'estado_id' => Estado::validado()->id,
            'area_id' => $this->gerencia->id,
            'user_id' => $this->empleado->id,
        ]);
    }

    #[Test]
    public function la_ficha_del_objetivo_registra_solo_los_campos_que_cambiaron(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(FichaObjetivo::class, ['objetivo' => $this->objetivo])
            ->call('activarEdicion')
            ->assertSet('form.fecha_objetivo', '2026-12-31')
            ->set('form.nombre', 'Reducir reclamos de clientes')
            ->set('mensaje', 'Precisión del alcance')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('objetivo-actualizado');

        $propuesta = $this->objetivo->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertEquals(['nombre' => 'Reducir reclamos de clientes'], $propuesta->data['campos']);
        $this->assertEquals('Reducir reclamos', $this->objetivo->fresh()->nombre);
    }

    #[Test]
    public function un_gerente_cambia_la_fecha_y_se_aplica_con_el_diff_legible(): void
    {
        Livewire::actingAs($this->gerente)
            ->test(FichaObjetivo::class, ['objetivo' => $this->objetivo])
            ->call('activarEdicion')
            ->set('form.fecha_objetivo', '2027-03-31')
            ->set('mensaje', 'Se corre el plazo')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertEquals('2027-03-31', $this->objetivo->fresh()->fecha_objetivo->format('Y-m-d'));
        $cambio = $this->objetivo->actualizaciones()->where('data->tipo', 'cambio')->sole();
        $this->assertEquals(['antes' => '2026-12-31', 'despues' => '2027-03-31'], $cambio->data['diff']['campos']['fecha_objetivo']);
    }

    #[Test]
    public function la_ficha_del_objetivo_sin_cambios_no_registra_nada(): void
    {
        Livewire::actingAs($this->gerente)
            ->test(FichaObjetivo::class, ['objetivo' => $this->objetivo])
            ->call('activarEdicion')
            ->set('mensaje', 'Nada')
            ->call('guardar')
            ->assertHasErrors('form');

        $this->assertCount(0, $this->objetivo->actualizaciones()->get());
    }

    #[Test]
    public function la_ficha_del_objetivo_devuelve_403_a_un_gerente_de_otra_gerencia(): void
    {
        $ajena = Area::create(['nombre' => 'Gerencia Ajena', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $ajena->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaObjetivo::class, ['objetivo' => $this->objetivo])
            ->call('activarEdicion')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(FichaObjetivo::class, ['objetivo' => $this->objetivo])
            ->set('form', ['nombre' => 'X', 'descripcion' => null, 'fecha_objetivo' => null])
            ->set('mensaje', 'Intento ajeno')
            ->call('guardar')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(Conversacion::class, ['modelType' => 'objetivo', 'modelId' => $this->objetivo->id])
            ->set('mensaje', 'Nota ajena')
            ->call('enviar')
            ->assertForbidden();

        $this->assertCount(0, $this->objetivo->actualizaciones()->get());
        $this->assertEquals('Reducir reclamos', $this->objetivo->fresh()->nombre);
    }
}
