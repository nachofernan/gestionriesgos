<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\RevisionInicial\Index;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\RevisionInicial;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pantalla de revisión de la carga inicial (D-024): qué ve cada rol, que
 * revisar y firmar pasan por la Policy (403) y por las reglas de firma.
 */
class RevisionInicialPantallaTest extends TestCase
{
    use RefreshDatabase;

    private Area $sistemas;

    private Area $finanzas;

    private User $gerente;

    private User $coordinador;

    private User $comite;

    private User $empleadoFinanzas;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
        config(['auditoria.revision_inicial.activa' => true]);

        $raiz = Area::create(['nombre' => 'Comité de Riesgo', 'tipo' => TipoArea::Gerencia]);
        $this->sistemas = Area::create(['nombre' => 'Gerencia Sistemas', 'tipo' => TipoArea::Gerencia, 'area_padre_id' => $raiz->id]);
        $coordinacion = Area::create(['nombre' => 'Coordinación Infraestructura', 'area_padre_id' => $this->sistemas->id]);
        $this->finanzas = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia, 'area_padre_id' => $raiz->id]);

        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->sistemas->id]);
        $this->coordinador = User::factory()->create(['rol' => 'empleado', 'area_id' => $coordinacion->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => $raiz->id]);
        $this->empleadoFinanzas = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->finanzas->id]);

        $this->control = Control::factory()->create([
            'nombre' => 'Backup diario de servidores',
            'area_id' => $coordinacion->id,
            'user_id' => $this->coordinador->id,
            'estado_id' => Estado::aprobado()->id,
            'mitigacion_default' => 3,
        ]);
        $this->control->riesgos()->attach(Riesgo::factory()->aprobado()->create(['area_id' => $this->sistemas->id])->id);
    }

    private function pantalla(User $user)
    {
        return Livewire::actingAs($user)->test(Index::class, ['tipo' => 'control']);
    }

    #[Test]
    public function el_gerente_firma_un_elemento_sin_inconsistencias(): void
    {
        $this->pantalla($this->gerente)
            ->set('tipo', 'control')
            ->assertSee('Backup diario de servidores')
            ->assertSeeHtml('x-on:click="abrir(')
            ->call('firmar', 'control', $this->control->id, $this->sistemas->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('revisiones_iniciales', [
            'revisable_id' => $this->control->id,
            'gerencia_id' => $this->sistemas->id,
            'firmado_por_id' => $this->gerente->id,
        ]);
    }

    #[Test]
    public function el_coordinador_marca_revisado_pero_no_puede_firmar(): void
    {
        $this->pantalla($this->coordinador)
            ->set('tipo', 'control')
            ->call('marcarRevisado', 'control', $this->control->id, $this->sistemas->id, 'Lo vimos.')
            ->assertHasNoErrors()
            ->call('firmar', 'control', $this->control->id, $this->sistemas->id)
            ->assertForbidden();

        $this->assertDatabaseHas('revisiones_iniciales', [
            'revisable_id' => $this->control->id,
            'revisado_por_id' => $this->coordinador->id,
            'firmado_en' => null,
        ]);
    }

    #[Test]
    public function con_errores_la_firma_se_rechaza(): void
    {
        $tarea = Tarea::factory()->create(['area_id' => $this->sistemas->id, 'estado_id' => Estado::aprobado()->id, 'fecha' => now()->addMonth()]);

        $this->pantalla($this->gerente)
            ->call('firmar', 'tarea', $tarea->id, $this->sistemas->id, 'Igual')
            ->assertHasErrors('revision');

        $this->assertDatabaseCount('revisiones_iniciales', 0);
    }

    #[Test]
    public function con_el_arreglo_validado_el_gerente_firma_sin_esperar_al_comite(): void
    {
        $riesgo = Riesgo::factory()->aprobado()->create(['area_id' => $this->sistemas->id, 'impacto' => 5, 'probabilidad' => 5]);
        $riesgo->controles()->attach($this->control->id);
        $objetivo = Objetivo::create(['nombre' => 'Continuidad operativa', 'area_id' => $this->sistemas->id, 'user_id' => $this->gerente->id]);
        $riesgo->actualizaciones()->create([
            'user_id' => $this->gerente->id,
            'mensaje' => 'Agregar objetivo',
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'cambio', 'relaciones' => ['objetivos' => ['agregar' => [$objetivo->id => []]]]],
        ]);

        $this->pantalla($this->gerente)
            ->call('firmar', 'riesgo', $riesgo->id, $this->sistemas->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('revisiones_iniciales', ['revisable_id' => $riesgo->id, 'firmado_por_id' => $this->gerente->id]);
        $this->assertSame(0, $riesgo->objetivos()->count());
    }

    /** Riesgo sin objetivo con una propuesta del coordinador en borrador. */
    private function riesgoConPropuestaDeEmpleado(array $data): array
    {
        $riesgo = Riesgo::factory()->aprobado()->create(['area_id' => $this->sistemas->id, 'impacto' => 5, 'probabilidad' => 5]);
        $riesgo->controles()->attach($this->control->id);
        $propuesta = $riesgo->actualizaciones()->create([
            'user_id' => $this->coordinador->id,
            'mensaje' => 'Corrección de la carga',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'cambio', ...$data],
        ]);

        return [$riesgo, $propuesta];
    }

    private function agregarObjetivo(): array
    {
        $objetivo = Objetivo::create(['nombre' => 'Continuidad operativa', 'area_id' => $this->sistemas->id, 'user_id' => $this->gerente->id]);

        return ['relaciones' => ['objetivos' => ['agregar' => [$objetivo->id => []]]]];
    }

    #[Test]
    public function validar_y_firmar_valida_los_cambios_y_firma(): void
    {
        [$riesgo, $propuesta] = $this->riesgoConPropuestaDeEmpleado($this->agregarObjetivo());

        $this->pantalla($this->gerente)
            ->set('tipo', 'riesgo')
            ->assertSee('Validar y firmar')
            ->call('validarYFirmar', 'riesgo', $riesgo->id, $this->sistemas->id)
            ->assertHasNoErrors();

        $this->assertEquals('validado', $propuesta->fresh()->estado->nombre);
        $revision = RevisionInicial::where('revisable_id', $riesgo->id)->firstOrFail();
        $this->assertEquals($this->gerente->id, $revision->firmado_por_id);
        $this->assertSame(['sin_objetivo'], $revision->arreglos_pendientes);
    }

    #[Test]
    public function con_cambios_sin_validar_no_se_firma_a_secas(): void
    {
        [$riesgo] = $this->riesgoConPropuestaDeEmpleado(['campos' => ['descripcion' => 'Más clara']]);
        $riesgo->objetivos()->attach(Objetivo::create(['nombre' => 'Otro', 'area_id' => $this->sistemas->id])->id);

        $this->pantalla($this->gerente)
            ->call('firmar', 'riesgo', $riesgo->id, $this->sistemas->id)
            ->assertHasErrors('revision');

        $this->assertDatabaseCount('revisiones_iniciales', 0);
    }

    #[Test]
    public function si_la_firma_no_procede_no_se_valida_nada(): void
    {
        [$riesgo, $propuesta] = $this->riesgoConPropuestaDeEmpleado(['campos' => ['descripcion' => 'Más clara']]);

        $this->pantalla($this->gerente)
            ->call('validarYFirmar', 'riesgo', $riesgo->id, $this->sistemas->id)
            ->assertHasErrors('revision');

        $this->assertEquals('borrador', $propuesta->fresh()->estado->nombre);
        $this->assertDatabaseCount('revisiones_iniciales', 0);
    }

    #[Test]
    public function el_coordinador_no_puede_validar_y_firmar(): void
    {
        [$riesgo, $propuesta] = $this->riesgoConPropuestaDeEmpleado($this->agregarObjetivo());

        $this->pantalla($this->coordinador)
            ->call('validarYFirmar', 'riesgo', $riesgo->id, $this->sistemas->id)
            ->assertForbidden();

        $this->assertEquals('borrador', $propuesta->fresh()->estado->nombre);
    }

    #[Test]
    public function con_advertencias_la_firma_exige_comentario(): void
    {
        $plan = PlanAccion::factory()->create(['area_id' => $this->sistemas->id, 'estado_id' => Estado::aprobado()->id]);

        $this->pantalla($this->gerente)
            ->call('firmar', 'plan', $plan->id, $this->sistemas->id)
            ->assertHasErrors('comentario')
            ->call('firmar', 'plan', $plan->id, $this->sistemas->id, 'Se cargan las tareas en noviembre.')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('revisiones_iniciales', ['revisable_id' => $plan->id, 'comentario_firma' => 'Se cargan las tareas en noviembre.']);
    }

    #[Test]
    public function el_comite_ve_todo_pero_no_revisa_ni_firma(): void
    {
        $this->pantalla($this->comite)
            ->set('tipo', 'control')
            ->assertSee('Backup diario de servidores')
            ->assertDontSeeHtml('x-on:click="abrir(')
            ->call('marcarRevisado', 'control', $this->control->id, $this->sistemas->id)
            ->assertForbidden();
    }

    #[Test]
    public function un_empleado_de_otra_gerencia_no_ve_ni_revisa_el_elemento(): void
    {
        $this->pantalla($this->empleadoFinanzas)
            ->set('tipo', 'control')
            ->assertDontSee('Backup diario de servidores')
            ->call('marcarRevisado', 'control', $this->control->id, $this->sistemas->id)
            ->assertForbidden();
    }

    #[Test]
    public function con_el_flag_apagado_la_pantalla_da_404(): void
    {
        config(['auditoria.revision_inicial.activa' => false]);

        $this->actingAs($this->gerente)
            ->get(route('auditoria.revision-inicial.index'))
            ->assertNotFound();
    }
}
