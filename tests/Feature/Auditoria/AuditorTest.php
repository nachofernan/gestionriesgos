<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\Conversacion;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\PlanAccion\Show\GestionTareas;
use App\Livewire\Auditoria\Riesgo\Show\FichaRiesgo;
use App\Livewire\Auditoria\Riesgo\Show\GestionControles;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rol auditor (D-020): ve como un empleado de su área, propone cambios sobre
 * cualquier elemento validado o aprobado (ficha, bloques, conversación,
 * recálculo), sus propuestas nacen en borrador y las valida el gerente que
 * gestiona el elemento. No crea, no elimina, no valida/aprueba/rechaza, no deja
 * voto de gerencia y no ve borradores de otras gerencias.
 */
class AuditorTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerenciaAuditoria;

    private Area $gerenciaA;

    private Area $gerenciaB;

    private User $auditor;

    private User $gerenteA;

    private User $gerenteB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerenciaAuditoria = Area::create(['nombre' => 'Auditoría Interna', 'tipo' => TipoArea::Gerencia]);
        $this->gerenciaA = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $this->gerenciaB = Area::create(['nombre' => 'Gerencia B', 'tipo' => TipoArea::Gerencia]);

        $this->auditor = User::factory()->create(['rol' => 'auditor', 'area_id' => $this->gerenciaAuditoria->id]);
        $this->gerenteA = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerenciaA->id]);
        $this->gerenteB = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerenciaB->id]);
    }

    private function riesgo(Area $area, string $estado): Riesgo
    {
        return Riesgo::factory()->{$estado}()->create([
            'area_id' => $area->id,
            'nombre' => 'Original',
            'descripcion' => 'Descripción original',
            'respuesta' => RespuestaRiesgo::Evitar,
            'impacto' => 5,
            'probabilidad' => 5,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
    }

    private function proponerDescripcion(User $user, Riesgo $riesgo, string $descripcion)
    {
        return Livewire::actingAs($user)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->set('mensaje', 'Lo detectó auditoría')
            ->set('form.descripcion', $descripcion)
            ->call('guardar');
    }

    // -------------------------------------------------------
    // Dónde puede proponer
    // -------------------------------------------------------

    #[Test]
    public function el_auditor_puede_proponer_sobre_lo_validado_y_aprobado_de_otra_gerencia(): void
    {
        $validado = $this->riesgo($this->gerenciaA, 'validado');
        $aprobado = $this->riesgo($this->gerenciaA, 'aprobado');
        $control = Control::factory()->create(['area_id' => $this->gerenciaA->id, 'estado_id' => Estado::aprobado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->gerenciaA->id, 'estado_id' => Estado::validado()->id]);
        $tarea = Tarea::factory()->create(['area_id' => $this->gerenciaA->id, 'estado_id' => Estado::aprobado()->id]);

        foreach ([$validado, $aprobado, $control, $plan, $tarea] as $entidad) {
            $this->assertTrue($this->auditor->can('proponer', $entidad), class_basename($entidad));
            // Proponer no es gestionar: no habilita mutar directo.
            $this->assertFalse($this->auditor->can('update', $entidad), class_basename($entidad));
        }
    }

    #[Test]
    public function el_auditor_no_puede_proponer_sobre_un_borrador_de_otra_gerencia(): void
    {
        $borrador = $this->riesgo($this->gerenciaA, 'borrador');

        $this->assertFalse($this->auditor->can('view', $borrador));
        $this->assertFalse($this->auditor->can('proponer', $borrador));

        Livewire::actingAs($this->auditor)
            ->test(FichaRiesgo::class, ['riesgo' => $borrador])
            ->call('activarEdicion')
            ->assertForbidden();

        Livewire::actingAs($this->auditor)
            ->test(Conversacion::class, ['modelType' => 'riesgo', 'modelId' => $borrador->id])
            ->set('mensaje', 'Nota sobre un borrador ajeno')
            ->call('enviar')
            ->assertForbidden();

        $this->assertCount(0, $borrador->actualizaciones()->get());
    }

    #[Test]
    public function el_auditor_no_ve_borradores_de_otra_gerencia_pero_si_los_de_su_area(): void
    {
        $borradorAjeno = $this->riesgo($this->gerenciaA, 'borrador');
        $validadoAjeno = $this->riesgo($this->gerenciaA, 'validado');
        $borradorPropio = $this->riesgo($this->gerenciaAuditoria, 'borrador');

        $visibles = Riesgo::visiblePara($this->auditor)->pluck('id')->all();

        $this->assertNotContains($borradorAjeno->id, $visibles);
        $this->assertContains($validadoAjeno->id, $visibles);
        $this->assertContains($borradorPropio->id, $visibles);
    }

    // -------------------------------------------------------
    // Sus propuestas nacen en borrador
    // -------------------------------------------------------

    #[Test]
    public function la_propuesta_del_auditor_sobre_un_riesgo_ajeno_nace_en_borrador_y_no_se_aplica(): void
    {
        $riesgo = $this->riesgo($this->gerenciaA, 'aprobado');

        $this->proponerDescripcion($this->auditor, $riesgo, 'Nueva descripción')->assertHasNoErrors();

        $this->assertEquals('Descripción original', $riesgo->refresh()->descripcion);
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertEquals('borrador', $propuesta->estado->nombre);
        $this->assertEquals($this->auditor->id, $propuesta->user_id);
    }

    #[Test]
    public function la_propuesta_del_auditor_en_su_propia_area_tambien_nace_en_borrador(): void
    {
        $riesgo = $this->riesgo($this->gerenciaAuditoria, 'validado');

        $this->proponerDescripcion($this->auditor, $riesgo, 'Nueva descripción')->assertHasNoErrors();

        $this->assertEquals('Descripción original', $riesgo->refresh()->descripcion);
        $this->assertEquals('borrador', $riesgo->actualizaciones()->propuestasPendientes('campos')->sole()->estado->nombre);
    }

    #[Test]
    public function el_auditor_edita_directo_un_borrador_de_su_area_pero_no_lo_elimina(): void
    {
        $borradorPropio = $this->riesgo($this->gerenciaAuditoria, 'borrador');

        $this->assertTrue($this->auditor->can('update', $borradorPropio));
        $this->assertFalse($this->auditor->can('delete', $borradorPropio));

        $this->actingAs($this->auditor)
            ->delete(route('auditoria.riesgos.destroy', $borradorPropio))
            ->assertForbidden();
    }

    #[Test]
    public function el_auditor_propone_controles_por_elemento_en_un_riesgo_ajeno(): void
    {
        $riesgo = $this->riesgo($this->gerenciaA, 'validado');
        $control = Control::factory()->create(['area_id' => $this->gerenciaA->id, 'estado_id' => Estado::aprobado()->id]);

        Livewire::actingAs($this->auditor)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $control->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertCount(0, $riesgo->refresh()->controles);
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('controles')->sole();
        $this->assertEquals('borrador', $propuesta->estado->nombre);
    }

    #[Test]
    public function el_auditor_recalcula_un_riesgo_ajeno_como_propuesta_en_borrador(): void
    {
        $riesgo = $this->riesgo($this->gerenciaA, 'validado');

        $this->actingAs($this->auditor)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'probabilidad_respuestas' => [1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2],
            'impacto_respuestas' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
        ])->assertRedirect(route('auditoria.riesgos.show', $riesgo));

        $riesgo->refresh();
        $this->assertEquals(5, $riesgo->impacto);
        $this->assertEquals(5, $riesgo->probabilidad);
        $propuesta = Actualizacion::latest('id')->first();
        $this->assertEquals('borrador', $propuesta->estado->nombre);
        $this->assertEquals(10, $propuesta->data['campos']['probabilidad']);
    }

    #[Test]
    public function el_auditor_deja_notas_en_un_elemento_ajeno_validado(): void
    {
        $riesgo = $this->riesgo($this->gerenciaA, 'validado');

        Livewire::actingAs($this->auditor)
            ->test(Conversacion::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->set('mensaje', 'Esto cambió respecto del último relevamiento')
            ->call('enviar')
            ->assertHasNoErrors();

        $nota = $riesgo->actualizaciones()->sole();
        $this->assertNull($nota->estado_id);
        $this->assertEquals($this->auditor->id, $nota->user_id);
    }

    // -------------------------------------------------------
    // Quién valida
    // -------------------------------------------------------

    #[Test]
    public function el_gerente_responsable_valida_la_propuesta_del_auditor_y_otro_gerente_recibe_403(): void
    {
        $riesgo = $this->riesgo($this->gerenciaA, 'validado');
        $this->proponerDescripcion($this->auditor, $riesgo, 'Nueva descripción');
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('campos')->sole();

        Livewire::actingAs($this->gerenteB)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $propuesta->id)
            ->assertForbidden();
        $this->assertEquals('Descripción original', $riesgo->refresh()->descripcion);

        Livewire::actingAs($this->gerenteA)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $propuesta->id);
        $this->assertEquals('Nueva descripción', $riesgo->refresh()->descripcion);
    }

    #[Test]
    public function la_propuesta_del_auditor_en_un_riesgo_compartido_espera_el_voto_de_todas_las_gerencias(): void
    {
        // El caso feo: el riesgo es compartido con la propia gerencia del auditor.
        // Aun así no vota: su gerencia tiene que validar por medio de su gerente.
        $riesgo = $this->riesgo($this->gerenciaA, 'validado');
        $riesgo->areas()->syncWithoutDetaching([$this->gerenciaAuditoria->id]);
        $gerenteAuditoria = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerenciaAuditoria->id]);

        $this->proponerDescripcion($this->auditor, $riesgo, 'Nueva descripción')->assertHasNoErrors();
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertEquals('borrador', $propuesta->estado->nombre);
        $this->assertCount(0, $propuesta->validacionesGerencia);

        Livewire::actingAs($this->gerenteA)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $propuesta->id);
        $this->assertEquals('Descripción original', $riesgo->refresh()->descripcion);

        Livewire::actingAs($gerenteAuditoria)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $propuesta->id);
        $this->assertEquals('Nueva descripción', $riesgo->refresh()->descripcion);
    }

    // -------------------------------------------------------
    // Lo que no puede
    // -------------------------------------------------------

    #[Test]
    public function el_auditor_no_puede_crear_elementos(): void
    {
        $this->actingAs($this->auditor)->get(route('auditoria.riesgos.create'))->assertForbidden();
        $this->actingAs($this->auditor)->get(route('auditoria.controles.create'))->assertForbidden();
        $this->actingAs($this->auditor)->post(route('auditoria.riesgos.store'), [
            'area_id' => $this->gerenciaAuditoria->id,
        ])->assertForbidden();

        $plan = PlanAccion::factory()->create(['area_id' => $this->gerenciaAuditoria->id, 'estado_id' => Estado::validado()->id]);
        Livewire::actingAs($this->auditor)
            ->test(GestionTareas::class, ['plan' => $plan])
            ->assertSet('puedeCrearTarea', false)
            ->set('nuevaNombre', 'Tarea colada')
            ->call('guardarNuevaTarea')
            ->assertForbidden();
        $this->assertDatabaseMissing('tareas', ['nombre' => 'Tarea colada']);
    }

    #[Test]
    public function el_auditor_no_puede_validar_aprobar_ni_rechazar(): void
    {
        $borradorPropio = $this->riesgo($this->gerenciaAuditoria, 'borrador');
        $validado = $this->riesgo($this->gerenciaA, 'validado');

        $this->assertFalse($this->auditor->can('validar', $borradorPropio));
        $this->assertFalse($this->auditor->can('rechazar', $borradorPropio));
        $this->assertFalse($this->auditor->can('aprobar', $validado));

        $empleadoA = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerenciaA->id]);
        $this->proponerDescripcion($empleadoA, $validado, 'Cambio del empleado');
        $propuesta = $validado->actualizaciones()->propuestasPendientes('campos')->sole();

        $this->assertFalse($this->auditor->can('validar', $propuesta));
        $this->assertFalse($this->auditor->can('rechazar', $propuesta));
    }
}
