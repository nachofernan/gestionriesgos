<?php

namespace Tests\Feature\Auditoria;

use App\Livewire\Auditoria\Actualizaciones\AccionActualizacionModal;
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
 * Cubre el modal liviano de Pendientes para validar/aprobar/rechazar una
 * Actualizacion puntual (sin abrir el historial completo de la entidad).
 * Ver AccionActualizacionModal y su motivación en docs/updates.
 */
class AccionActualizacionModalTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerAdmin;

    private Area $sectA;

    private User $canela; // gerente gerAdmin

    private User $lucia;  // comité

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $comite = Area::create(['nombre' => 'Comité', 'area_padre_id' => null]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $comite->id]);
        $this->sectA = Area::create(['nombre' => 'Sector A', 'area_padre_id' => $this->gerAdmin->id]);

        $this->canela = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerAdmin->id]);
        $this->lucia = User::factory()->create(['rol' => 'comite',  'area_id' => $comite->id]);
    }

    private function objetivoConActualizacion(int $estadoObjetivo, int $estadoActualizacion): array
    {
        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo original',
            'estado_id' => $estadoObjetivo,
            'area_id' => $this->sectA->id,
            'user_id' => $this->canela->id,
        ]);

        $actualizacion = $objetivo->actualizaciones()->create([
            'user_id' => $this->canela->id,
            'mensaje' => 'Propongo cambiar el nombre',
            'estado_id' => $estadoActualizacion,
            'data' => ['tipo' => 'cambio', 'campos' => ['nombre' => 'Objetivo nuevo']],
        ]);

        return [$objetivo, $actualizacion];
    }

    #[Test]
    public function el_gerente_puede_validar_una_actualizacion_de_su_area_desde_el_modal(): void
    {
        [$objetivo, $actualizacion] = $this->objetivoConActualizacion(Estado::aprobado()->id, Estado::borrador()->id);

        Livewire::actingAs($this->canela)
            ->test(AccionActualizacionModal::class)
            ->call('abrir', $actualizacion->id, 'validar')
            ->assertSet('abierto', true)
            ->call('confirmar')
            ->assertSet('abierto', false)
            ->assertDispatched('actualizacion-procesada', id: $actualizacion->id);

        $this->assertEquals('validado', $actualizacion->fresh()->estado->nombre);
    }

    #[Test]
    public function el_comite_puede_aprobar_una_actualizacion_validada_y_se_aplican_los_cambios(): void
    {
        [$objetivo, $actualizacion] = $this->objetivoConActualizacion(Estado::validado()->id, Estado::validado()->id);

        Livewire::actingAs($this->lucia)
            ->test(AccionActualizacionModal::class)
            ->call('abrir', $actualizacion->id, 'aprobar')
            ->assertSet('abierto', true)
            ->call('confirmar')
            ->assertDispatched('actualizacion-procesada', id: $actualizacion->id);

        $this->assertEquals('aprobado', $actualizacion->fresh()->estado->nombre);
        $this->assertEquals('Objetivo nuevo', $objetivo->fresh()->nombre);
    }

    #[Test]
    public function rechazar_marca_la_actualizacion_como_borrada_sin_tocar_la_entidad(): void
    {
        [$objetivo, $actualizacion] = $this->objetivoConActualizacion(Estado::aprobado()->id, Estado::borrador()->id);

        Livewire::actingAs($this->canela)
            ->test(AccionActualizacionModal::class)
            ->call('abrir', $actualizacion->id, 'rechazar')
            ->call('confirmar')
            ->assertDispatched('actualizacion-procesada', id: $actualizacion->id);

        $this->assertEquals('borrado', $actualizacion->fresh()->estado->nombre);
        $this->assertEquals('Objetivo original', $objetivo->fresh()->nombre);
    }

    #[Test]
    public function un_gerente_de_otra_area_no_puede_abrir_el_modal_para_validar(): void
    {
        [, $actualizacion] = $this->objetivoConActualizacion(Estado::aprobado()->id, Estado::borrador()->id);

        $otraArea = Area::create(['nombre' => 'Otra gerencia', 'area_padre_id' => null]);
        $otroGerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $otraArea->id]);

        Livewire::actingAs($otroGerente)
            ->test(AccionActualizacionModal::class)
            ->call('abrir', $actualizacion->id, 'validar')
            ->assertSet('abierto', false);

        $this->assertEquals('borrador', $actualizacion->fresh()->estado->nombre);
    }
}
