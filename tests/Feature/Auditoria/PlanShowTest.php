<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\Conversacion;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\PlanAccion\Show\FichaPlan;
use App\Livewire\Auditoria\PlanAccion\Show\GestionTareas;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rediseño de planaccion/show: las Tareas del plan pasan al modelo de propuesta
 * por elemento de riesgo/show (D-016). Cada alta o baja es su propia propuesta,
 * una tarea con propuesta pendiente queda bloqueada, y como el avance del plan sale
 * de sus tareas, una propuesta no mueve el avance ni el residual hasta aplicarse.
 * Cubre también la Ficha del plan y la conversación.
 */
class PlanShowTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $empleado;

    private User $gerente;

    private User $comite;

    private PlanAccion $plan;

    private Tarea $tareaUno;

    private Tarea $tareaDos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);

        $this->plan = PlanAccion::factory()->create([
            'nombre' => 'Plan de proveedores',
            'estado_id' => Estado::validado()->id,
            'area_id' => $this->gerencia->id,
        ]);
        $this->tareaUno = $this->tarea('Relevar', 100);
        $this->tareaDos = $this->tarea('Negociar', 100);
        $this->plan->tareas()->attach([$this->tareaUno->id, $this->tareaDos->id]);
    }

    private function tarea(string $nombre, int $avance): Tarea
    {
        return Tarea::factory()->create([
            'nombre' => $nombre,
            'porcentaje_avance' => $avance,
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerencia->id,
        ]);
    }

    private function propuestas(): Collection
    {
        return $this->plan->actualizaciones()->propuestasPendientes('tareas')->orderBy('id')->get();
    }

    private function idsEnPivot(): array
    {
        return $this->plan->tareas()->pluck('tareas.id')->sort()->values()->all();
    }

    #[Test]
    public function un_empleado_que_agrega_y_quita_tareas_genera_una_propuesta_por_elemento(): void
    {
        $nueva = $this->tarea('Auditar', 0);

        Livewire::actingAs($this->empleado)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->call('activarEdicion')
            ->call('agregar', $nueva->id)
            ->call('quitar', $this->tareaUno->id)
            ->call('guardar')
            ->assertDispatched('plan-actualizado');

        $propuestas = $this->propuestas();
        $this->assertCount(2, $propuestas);
        $this->assertEquals(['agregar' => [$nueva->id => []]], $propuestas[0]->data['relaciones']['tareas']);
        $this->assertEquals(['detach' => [$this->tareaUno->id]], $propuestas[1]->data['relaciones']['tareas']);
        $this->assertEquals([$this->tareaUno->id, $this->tareaDos->id], $this->idsEnPivot());

        // Validar sólo el alta aplica sólo ese elemento; la baja sigue pendiente.
        Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'plan', 'modelId' => $this->plan->id, 'variante' => 'timeline'])
            ->call('validarActualizacion', $propuestas[0]->id);

        $this->assertEquals([$this->tareaUno->id, $this->tareaDos->id, $nueva->id], $this->idsEnPivot());
        $this->assertCount(1, $this->propuestas());
    }

    #[Test]
    public function una_tarea_con_propuesta_pendiente_no_se_puede_volver_a_tocar(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->call('activarEdicion')
            ->call('quitar', $this->tareaUno->id)
            ->call('guardar');

        Livewire::actingAs($this->empleado)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->assertViewHas('bloqueados', [$this->tareaUno->id])
            ->call('activarEdicion')
            ->call('quitar', $this->tareaUno->id)
            ->assertSet('seleccionados', fn ($sel) => collect($sel)->pluck('id')->contains($this->tareaUno->id));
    }

    #[Test]
    public function una_tarea_propuesta_no_mueve_el_avance_del_plan_hasta_aplicarse(): void
    {
        // Plan aprobado y completo (100%): descuenta 6 de un riesgo de total 16.
        $this->plan->update(['estado_id' => Estado::aprobado()->id]);
        $riesgo = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id, 'impacto' => 8, 'probabilidad' => 8]);
        $riesgo->planesAccion()->attach($this->plan->id, ['mitigacion' => 6]);
        $residual = fn () => Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])->findOrFail($riesgo->id)->valor_residual;
        $this->assertEquals(10, $residual());

        $pendiente = $this->tarea('Documentar', 0);
        Livewire::actingAs($this->empleado)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->call('activarEdicion')
            ->call('agregar', $pendiente->id)
            ->call('guardar');

        $this->assertEquals(100, $this->plan->fresh()->avance);
        $this->assertEquals(10, $residual());

        $this->propuestas()->sole()->marcarAprobada($this->comite);

        $this->assertEquals(67, $this->plan->fresh()->avance);
        $this->assertEquals(16, $residual());
    }

    #[Test]
    public function el_comite_sobre_un_plan_aprobado_aplica_las_tareas_en_el_acto(): void
    {
        $this->plan->update(['estado_id' => Estado::aprobado()->id]);
        $nueva = $this->tarea('Auditar', 0);

        Livewire::actingAs($this->comite)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->call('activarEdicion')
            ->call('agregar', $nueva->id)
            ->call('guardar');

        $this->assertContains($nueva->id, $this->idsEnPivot());
        $this->assertCount(0, $this->propuestas());
        $cambio = $this->plan->actualizaciones()->where('data->tipo', 'cambio')->sole();
        $this->assertEquals([['id' => $nueva->id, 'nombre' => 'Auditar']], $cambio->data['diff']['relaciones']['tareas']['agrega']);
    }

    #[Test]
    public function la_ficha_del_plan_registra_solo_los_campos_que_cambiaron(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(FichaPlan::class, ['plan' => $this->plan])
            ->call('activarEdicion')
            ->set('form.nombre', 'Plan de proveedores críticos')
            ->set('mensaje', 'Se acota el alcance')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('plan-actualizado');

        $propuesta = $this->plan->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertEquals(['nombre' => 'Plan de proveedores críticos'], $propuesta->data['campos']);
        $this->assertEquals('Plan de proveedores', $this->plan->fresh()->nombre);
    }

    #[Test]
    public function la_ficha_del_plan_devuelve_403_a_un_gerente_de_otra_gerencia(): void
    {
        $ajena = Area::create(['nombre' => 'Gerencia Ajena', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $ajena->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaPlan::class, ['plan' => $this->plan])
            ->call('activarEdicion')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(FichaPlan::class, ['plan' => $this->plan])
            ->set('form', ['nombre' => 'X', 'descripcion' => null])
            ->set('mensaje', 'Intento ajeno')
            ->call('guardar')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->call('activarEdicion')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(Conversacion::class, ['modelType' => 'plan', 'modelId' => $this->plan->id])
            ->set('mensaje', 'Nota ajena')
            ->call('enviar')
            ->assertForbidden();

        $this->assertCount(0, $this->plan->actualizaciones()->get());
    }
}
