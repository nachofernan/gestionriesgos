<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Livewire\Auditoria\ValidacionCascadaModal;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre el flag sinRedireccion agregado para la pantalla de Pendientes: cuando
 * viene en true, confirmar() no redirige (a diferencia del uso normal desde
 * los show() de cada entidad) y en cambio avisa por evento de navegador para
 * que sólo esa fila se actualice.
 */
class ValidacionCascadaModalTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $area = Area::create(['nombre' => 'Gerencia', 'area_padre_id' => null]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $area->id]);

        $this->riesgo = Riesgo::factory()->borrador()->create([
            'area_id' => $area->id,
            'nombre' => 'Riesgo test',
            'respuesta' => RespuestaRiesgo::Aceptar,
        ]);

        // motivosBloqueoValidacion() exige al menos un objetivo asociado.
        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo del riesgo',
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $area->id,
            'user_id' => $this->gerente->id,
        ]);
        $this->riesgo->objetivos()->attach($objetivo->id);
    }

    private Riesgo $riesgo;

    #[Test]
    public function con_sinredireccion_no_redirige_y_avisa_por_evento(): void
    {
        Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $this->riesgo->id, 'validar', true)
            ->call('confirmar')
            ->assertDispatched('cascada-procesada', tipo: 'riesgo', id: $this->riesgo->id)
            ->assertNoRedirect();

        $this->assertEquals('validado', $this->riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function sin_sinredireccion_mantiene_el_comportamiento_de_redirigir(): void
    {
        Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $this->riesgo->id, 'validar')
            ->call('confirmar')
            ->assertRedirect();

        $this->assertEquals('validado', $this->riesgo->fresh()->estado->nombre);
    }
}
