<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un mensaje puro (sin cambios de campo) se escribe y punto: no entra al ciclo
 * borrador→validado→aprobado, no aparece en Pendientes y no ofrece acciones de
 * validar/aprobar/rechazar. Antes de este fix, GestionActualizaciones::guardar()
 * igual le asignaba `data=['tipo'=>'cambio']` y un estado por rol, mezclándolo
 * con una propuesta de cambio real.
 */
class MensajePuroSinValidacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    #[Test]
    public function un_mensaje_puro_no_tiene_estado_ni_datos_de_cambio(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);
        $control = Control::factory()->create(['area_id' => $gerencia->id, 'estado_id' => Estado::validado()->id]);

        Livewire::actingAs($gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'control', 'modelId' => $control->id])
            ->call('abrirModal')
            ->set('mensaje', 'Sólo dejo una nota, sin cambiar nada')
            ->call('guardar')
            ->assertHasNoErrors();

        $actualizacion = Actualizacion::latest('id')->first();
        $this->assertNotNull($actualizacion);
        $this->assertNull($actualizacion->estado_id);
        $this->assertNull($actualizacion->data);
    }

    #[Test]
    public function un_mensaje_puro_no_aparece_en_pendientes_ni_admite_validar(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);
        $control = Control::factory()->create(['area_id' => $gerencia->id, 'estado_id' => Estado::validado()->id]);

        Livewire::actingAs($gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'control', 'modelId' => $control->id])
            ->call('abrirModal')
            ->set('mensaje', 'Sólo dejo una nota, sin cambiar nada')
            ->call('guardar');

        $actualizacion = Actualizacion::latest('id')->first();

        $respuesta = $this->actingAs($gerente)->get(route('auditoria.pendientes.index'));
        $respuesta->assertDontSee('Sólo dejo una nota, sin cambiar nada');

        $this->assertFalse(Gate::forUser($gerente)->allows('validar', $actualizacion));
        $this->assertFalse(Gate::forUser($gerente)->allows('rechazar', $actualizacion));
    }
}
