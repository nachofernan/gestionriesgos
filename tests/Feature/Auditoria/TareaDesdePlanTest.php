<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\PlanAccion\Show\GestionTareas;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tarea creada desde un plan (D-030): admite cualquier área, nace validada sólo
 * si la crea un gerente para su propia línea, queda asociada al plan en el acto
 * (el vínculo sigue a la tarea) y quien gestiona el plan la ve y la comenta,
 * pero la valida el gerente del área de la tarea.
 */
class TareaDesdePlanTest extends TestCase
{
    use RefreshDatabase;

    private Area $sectorAmbiente;

    private Area $sectorRrhh;

    private User $empleadoAmbiente;

    private User $gerenteAmbiente;

    private User $gerenteRrhh;

    private User $empleadoRrhh;

    private User $tercero;

    private PlanAccion $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $comite = Area::create(['nombre' => 'Comité', 'tipo' => TipoArea::Gerencia]);
        $gerAmbiente = Area::create(['nombre' => 'Gerencia Medio Ambiente', 'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]);
        $this->sectorAmbiente = Area::create(['nombre' => 'Gestión Ambiental', 'area_padre_id' => $gerAmbiente->id]);
        $gerRrhh = Area::create(['nombre' => 'Gerencia RRHH', 'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]);
        $this->sectorRrhh = Area::create(['nombre' => 'Capacitación', 'area_padre_id' => $gerRrhh->id]);
        $gerOtra = Area::create(['nombre' => 'Gerencia Finanzas', 'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]);

        $this->empleadoAmbiente = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectorAmbiente->id]);
        $this->gerenteAmbiente = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerAmbiente->id]);
        $this->gerenteRrhh = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerRrhh->id]);
        $this->empleadoRrhh = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectorRrhh->id]);
        $this->tercero = User::factory()->create(['rol' => 'gerente', 'area_id' => $gerOtra->id]);

        $this->plan = PlanAccion::factory()->create([
            'area_id' => $this->sectorAmbiente->id,
            'estado_id' => Estado::validado()->id,
        ]);
    }

    private function crear(User $user, array $campos)
    {
        $componente = Livewire::actingAs($user)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->call('abrirFormNuevaTarea');
        foreach (['nombre' => 'Capacitación en residuos'] + $campos as $campo => $valor) {
            $componente->set("nueva.{$campo}", $valor);
        }

        return $componente->call('guardarNuevaTarea');
    }

    private function tareaDeRrhhEnBorrador(): Tarea
    {
        $this->crear($this->empleadoAmbiente, ['area_id' => (string) $this->sectorRrhh->id])->assertHasNoErrors();

        return Tarea::sole()->load(['estado', 'planesAccion']);
    }

    #[Test]
    public function un_empleado_crea_desde_su_plan_una_tarea_de_otra_area(): void
    {
        $this->crear($this->empleadoAmbiente, [
            'descripcion' => 'Curso obligatorio para el personal de planta',
            'area_id' => (string) $this->sectorRrhh->id,
            'user_id' => (string) $this->empleadoRrhh->id,
            'porcentaje_avance' => 10,
        ])->assertHasNoErrors();

        $tarea = Tarea::sole();
        $this->assertSame('borrador', $tarea->estado->nombre);
        $this->assertEquals($this->sectorRrhh->id, $tarea->area_id);
        $this->assertEquals($this->empleadoRrhh->id, $tarea->user_id);
        $this->assertSame('Curso obligatorio para el personal de planta', $tarea->descripcion);
        $this->assertTrue($this->plan->tareas()->whereKey($tarea->id)->exists());
        // El log de alta lleva el mismo estado que la tarea (antes decía "aprobado").
        $this->assertEquals($tarea->estado_id, $tarea->actualizaciones()->sole()->estado_id);
    }

    #[Test]
    public function un_gerente_crea_una_tarea_de_su_linea_y_nace_validada(): void
    {
        $this->crear($this->gerenteAmbiente, ['area_id' => (string) $this->sectorAmbiente->id])->assertHasNoErrors();

        $tarea = Tarea::sole();
        $this->assertSame('validado', $tarea->estado->nombre);
        $this->assertSame('validado', $tarea->actualizaciones()->sole()->estado->nombre);
    }

    #[Test]
    public function un_gerente_que_crea_para_otra_area_la_deja_en_borrador(): void
    {
        $this->crear($this->gerenteAmbiente, ['area_id' => (string) $this->sectorRrhh->id])->assertHasNoErrors();

        $this->assertSame('borrador', Tarea::sole()->estado->nombre);
    }

    #[Test]
    public function la_valida_el_gerente_de_su_area_y_no_el_del_plan(): void
    {
        $tarea = $this->tareaDeRrhhEnBorrador();

        $this->assertTrue($this->gerenteRrhh->can('validar', $tarea));
        $this->assertFalse($this->gerenteAmbiente->can('validar', $tarea));
        $this->assertFalse($this->gerenteAmbiente->can('update', $tarea));

        $this->actingAs($this->gerenteAmbiente)->post(route('auditoria.tareas.validar', $tarea))->assertForbidden();
        $this->actingAs($this->gerenteAmbiente)->get(route('auditoria.tareas.edit', $tarea))->assertForbidden();

        $this->actingAs($this->gerenteRrhh)->post(route('auditoria.tareas.validar', $tarea));
        $this->assertSame('validado', $tarea->fresh()->estado->nombre);
    }

    #[Test]
    public function quien_gestiona_el_plan_ve_la_tarea_en_borrador_de_otra_area(): void
    {
        $tarea = $this->tareaDeRrhhEnBorrador();

        $this->assertTrue($this->empleadoAmbiente->can('view', $tarea));
        $this->actingAs($this->empleadoAmbiente)->get(route('auditoria.tareas.show', $tarea))->assertOk();
    }

    #[Test]
    public function quien_gestiona_el_plan_puede_comentar_la_tarea_en_borrador_de_otra_area(): void
    {
        $tarea = $this->tareaDeRrhhEnBorrador();

        $this->assertTrue($this->empleadoAmbiente->can('comentar', $tarea));
        $this->assertFalse($this->empleadoAmbiente->can('proponer', $tarea));
    }

    #[Test]
    public function un_tercero_que_no_gestiona_el_plan_no_ve_la_tarea_en_borrador(): void
    {
        $tarea = $this->tareaDeRrhhEnBorrador();

        $this->assertFalse($this->tercero->can('view', $tarea));
        $this->actingAs($this->tercero)->get(route('auditoria.tareas.show', $tarea))->assertForbidden();
    }

    #[Test]
    public function quien_no_gestiona_el_plan_no_puede_crear_tareas_desde_el(): void
    {
        Livewire::actingAs($this->tercero)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->assertSet('puedeCrearTarea', false)
            ->call('abrirFormNuevaTarea')
            ->assertForbidden();

        Livewire::actingAs($this->tercero)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->set('nueva.nombre', 'Intrusa')
            ->set('nueva.area_id', (string) $this->sectorRrhh->id)
            ->call('guardarNuevaTarea')
            ->assertForbidden();

        $this->assertSame(0, Tarea::count());
    }

    #[Test]
    public function el_auditor_no_crea_tareas_desde_un_plan(): void
    {
        $auditor = User::factory()->create(['rol' => 'auditor', 'area_id' => $this->sectorAmbiente->id]);

        Livewire::actingAs($auditor)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->assertSet('puedeCrearTarea', false)
            ->set('nueva.nombre', 'Colada')
            ->set('nueva.area_id', (string) $this->sectorAmbiente->id)
            ->call('guardarNuevaTarea')
            ->assertForbidden();
    }

    #[Test]
    public function un_responsable_que_no_es_del_area_elegida_no_se_acepta(): void
    {
        $this->crear($this->empleadoAmbiente, [
            'area_id' => (string) $this->sectorRrhh->id,
            'user_id' => (string) $this->tercero->id,
        ])->assertHasErrors('nueva.user_id');

        $this->assertSame(0, Tarea::count());
    }

    #[Test]
    public function crear_desde_un_plan_validado_no_genera_propuesta_ni_mueve_el_avance(): void
    {
        $avanceAntes = $this->plan->fresh()->avance;

        $this->crear($this->empleadoAmbiente, ['area_id' => (string) $this->sectorRrhh->id, 'porcentaje_avance' => 100]);

        $this->assertSame(0, $this->plan->actualizaciones()->propuestasPendientes()->count());
        $this->assertSame($avanceAntes, $this->plan->fresh()->load('tareas.estado')->avance);
    }

    #[Test]
    public function si_se_rechaza_la_tarea_deja_de_aparecer_en_el_plan(): void
    {
        $tarea = $this->tareaDeRrhhEnBorrador();

        $this->actingAs($this->gerenteRrhh)->post(route('auditoria.tareas.rechazar', $tarea));

        Livewire::actingAs($this->empleadoAmbiente)
            ->test(GestionTareas::class, ['plan' => $this->plan])
            ->assertSet('seleccionados', fn ($sel) => ! collect($sel)->pluck('id')->contains($tarea->id));
    }
}
