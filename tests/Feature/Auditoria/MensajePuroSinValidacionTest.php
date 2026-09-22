<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Riesgo\Show\GestionAreas;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
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

    #[Test]
    public function un_mensaje_puro_sobre_un_riesgo_no_bloquea_gestionar_gerencias(): void
    {
        $gerA = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $gerB = Area::create(['nombre' => 'Gerencia B', 'tipo' => TipoArea::Gerencia]);
        $userA = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerA->id]);
        $riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $gerA->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
        $riesgo->areas()->syncWithoutDetaching([$gerA->id]);

        // Deja un mensaje puro (sin cambios de campo) sobre el riesgo.
        Livewire::actingAs($userA)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Nota sin cambios')
            ->call('guardar');

        // A diferencia de una propuesta de cambio real (que deja la propuesta en
        // borrador y bloquea GestionAreas::guardar()), esto no bloquea nada: pasar de
        // una a dos gerencias se aplica con la sola validación del proponente.
        Livewire::actingAs($userA)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $gerB->id)
            ->call('guardar')
            ->assertSet('error', null);

        $this->assertTrue($riesgo->refresh()->areas->pluck('id')->contains($gerB->id));
    }
}
