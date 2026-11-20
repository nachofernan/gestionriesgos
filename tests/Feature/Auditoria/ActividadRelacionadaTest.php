<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Objetivo\Show\RiesgosObjetivo;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\Testeo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * D-056: la actividad de un elemento también muestra, en solo lectura, lo que se
 * registró desde la contraparte (la asociación riesgo–objetivo vive en el riesgo,
 * la de plan–tarea en el plan), y los testeos de evidencia en el control y el riesgo.
 */
class ActividadRelacionadaTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private Objetivo $objetivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->gerente()->create(['area_id' => $this->gerencia->id]);
        $this->objetivo = Objetivo::create([
            'nombre' => 'Continuidad operativa',
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerencia->id,
            'user_id' => $this->gerente->id,
        ]);
    }

    private function riesgo(string $estado = 'borrador', ?Area $area = null): Riesgo
    {
        return Riesgo::factory()->create([
            'area_id' => ($area ?? $this->gerencia)->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create(['admite_peis' => false])->id,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
        ]);
    }

    private function actividad(string $tipo, int $id, ?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => $tipo, 'modelId' => $id, 'variante' => 'timeline']);
    }

    /** Propuesta por elemento como la que arma PropuestasEnBloque::proponerPorElemento(). */
    private function proponerRelacion($entidad, string $relacion, string $op, $elemento, string $estado = 'borrador')
    {
        return $entidad->actualizaciones()->create([
            'user_id' => $this->gerente->id,
            'mensaje' => "Propuesta sobre «{$elemento->nombre}»",
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
            'data' => [
                'tipo' => 'cambio',
                'relaciones' => [$relacion => $op === 'agrega' ? ['agregar' => [$elemento->id => []]] : ['detach' => [$elemento->id]]],
                'diff' => ['relaciones' => [$relacion => [$op => [['id' => $elemento->id, 'nombre' => $elemento->nombre]]]]],
            ],
        ]);
    }

    #[Test]
    public function asociar_un_riesgo_a_un_objetivo_aparece_en_la_actividad_del_objetivo(): void
    {
        $riesgo = $this->riesgo();

        // Desde objetivo/show: el registro queda en el historial del riesgo.
        Livewire::actingAs($this->gerente)->test(RiesgosObjetivo::class, ['objetivo' => $this->objetivo])
            ->call('agregar', $riesgo->id)->assertSet('error', '');
        $entrada = $riesgo->actualizaciones()->where('mensaje', 'Objetivos asociados')->sole();
        $this->assertSame(0, $this->objetivo->actualizaciones()->count());

        $actualizaciones = $this->actividad('objetivo', $this->objetivo->id)->viewData('actualizaciones');

        $this->assertSame([$entrada->id], $actualizaciones->pluck('id')->all());
        $this->assertSame($riesgo->nombre, $actualizaciones->first()->origen_externo['nombre']);
        $this->assertSame(route('auditoria.riesgos.show', $riesgo->id), $actualizaciones->first()->origen_externo['url']);
    }

    #[Test]
    public function una_propuesta_pendiente_del_riesgo_se_ve_pendiente_en_el_objetivo_y_rechazada_al_rechazarse(): void
    {
        $riesgo = $this->riesgo('validado');
        $propuesta = $this->proponerRelacion($riesgo, 'objetivos', 'agrega', $this->objetivo);

        $this->assertContains($propuesta->id, $this->actividad('objetivo', $this->objetivo->id)->viewData('pendientesIds')->all());

        $propuesta->marcarRechazada($this->gerente, 'No corresponde a este objetivo.');

        $componente = $this->actividad('objetivo', $this->objetivo->id);
        $this->assertNotContains($propuesta->id, $componente->viewData('pendientesIds')->all());
        $this->assertSame('borrado', $componente->viewData('actualizaciones')->sole()->estado->nombre);
    }

    #[Test]
    public function la_entrada_derivada_no_ofrece_acciones_y_linkea_al_origen(): void
    {
        $riesgo = $this->riesgo('validado');
        $this->proponerRelacion($riesgo, 'objetivos', 'agrega', $this->objetivo);

        $this->actividad('objetivo', $this->objetivo->id)
            ->call('abrirActividad')
            ->assertSee("Registrado en el riesgo «{$riesgo->nombre}»", false)
            ->assertDontSee('validarActualizacion(')
            ->assertDontSee('¿Retirar esta propuesta?');

        // En el riesgo, la misma propuesta sí ofrece resolverla.
        $this->actividad('riesgo', $riesgo->id)
            ->call('abrirActividad')
            ->assertSee('validarActualizacion(');
    }

    #[Test]
    public function no_aparece_lo_que_viene_de_un_riesgo_que_el_usuario_no_puede_ver(): void
    {
        $otra = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $ajeno = $this->riesgo('borrador', $otra);
        $this->proponerRelacion($ajeno, 'objetivos', 'agrega', $this->objetivo);
        $propio = $this->riesgo('validado');
        $visible = $this->proponerRelacion($propio, 'objetivos', 'agrega', $this->objetivo);

        $this->assertFalse($this->gerente->can('view', $ajeno));
        $this->assertSame([$visible->id], $this->actividad('objetivo', $this->objetivo->id)->viewData('actualizaciones')->pluck('id')->all());

        // Su propio gerente sí lo ve.
        $gerenteAjeno = User::factory()->gerente()->create(['area_id' => $otra->id]);
        $this->assertCount(2, $this->actividad('objetivo', $this->objetivo->id, $gerenteAjeno)->viewData('actualizaciones'));
    }

    #[Test]
    public function agregar_una_tarea_a_un_plan_aparece_en_la_actividad_de_la_tarea(): void
    {
        $plan = PlanAccion::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);
        $tarea = Tarea::factory()->create(['area_id' => $this->gerencia->id]);
        $otraTarea = Tarea::factory()->create(['area_id' => $this->gerencia->id]);
        $entrada = $this->proponerRelacion($plan, 'tareas', 'agrega', $tarea, 'aprobado');
        $this->proponerRelacion($plan, 'tareas', 'agrega', $otraTarea, 'aprobado');

        $actualizaciones = $this->actividad('tarea', $tarea->id)->viewData('actualizaciones');

        $this->assertSame([$entrada->id], $actualizaciones->pluck('id')->all());
        $this->assertSame('plan', $actualizaciones->first()->origen_externo['tipo']);
    }

    #[Test]
    public function eliminar_un_plan_no_duplica_la_entrada_en_sus_tareas(): void
    {
        $plan = PlanAccion::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::aprobado()->id]);
        $tarea = Tarea::factory()->create(['area_id' => $this->gerencia->id]);
        // Como la deja Actualizacion::registrarEliminacion() en el plan.
        $plan->actualizaciones()->create([
            'user_id' => $this->gerente->id,
            'mensaje' => 'Ya no aplica',
            'estado_id' => Estado::aprobado()->id,
            'data' => ['tipo' => 'cambio', 'eliminacion' => true, 'activated_by' => $this->gerente->name, 'diff' => [
                'campos' => ['estado' => ['antes' => 'aprobado', 'despues' => 'borrado']],
                'relaciones' => ['tareas' => ['quita' => [['id' => $tarea->id, 'nombre' => $tarea->nombre]]]],
            ]],
        ]);

        $this->assertCount(0, $this->actividad('tarea', $tarea->id)->viewData('actualizaciones'));
    }

    #[Test]
    public function un_testeo_que_confirma_el_porcentaje_aparece_en_el_control_y_en_el_riesgo(): void
    {
        [$control, $riesgo] = $this->relacionTesteable();
        $testeo = Testeo::registrar($control, $riesgo, User::factory()->auditor()->create(), 100, Carbon::yesterday(), 'Se revisó una muestra.');

        $this->assertCount(0, $riesgo->actualizaciones()->get());
        $this->assertSame([$testeo->id], $this->actividad('control', $control->id)->viewData('testeos')->pluck('id')->all());
        $this->assertSame([$testeo->id], $this->actividad('riesgo', $riesgo->id)->viewData('testeos')->pluck('id')->all());

        $this->actividad('control', $control->id)->call('abrirActividad', 'tst-'.$testeo->id)
            ->assertSee('confirma 100%')
            ->assertSee('Se revisó una muestra.');
    }

    #[Test]
    public function un_testeo_que_cambia_el_porcentaje_aparece_una_sola_vez_en_cada_lado(): void
    {
        [$control, $riesgo] = $this->relacionTesteable();
        $testeo = Testeo::registrar($control, $riesgo, User::factory()->auditor()->create(), 80, Carbon::yesterday(), 'Faltan evidencias.');

        // En el riesgo cuenta la Actualizacion que movió el %; el testeo no se repite.
        $enRiesgo = $this->actividad('riesgo', $riesgo->id);
        $this->assertSame(1, $enRiesgo->viewData('actualizaciones')->where('data.testeo_id', $testeo->id)->count());
        $this->assertCount(0, $enRiesgo->viewData('testeos'));

        // En el control cuenta el testeo; la Actualizacion del riesgo no se deriva.
        $enControl = $this->actividad('control', $control->id);
        $this->assertCount(0, $enControl->viewData('actualizaciones'));
        $this->assertSame([$testeo->id], $enControl->viewData('testeos')->pluck('id')->all());
    }

    #[Test]
    public function la_reevaluacion_del_auditor_aparece_en_el_control_y_no_en_sus_riesgos(): void
    {
        [$control, $riesgo] = $this->relacionTesteable();
        $auditor = User::factory()->auditor()->create();
        // Como la deja Control::reevaluar().
        $entrada = $control->actualizaciones()->create([
            'user_id' => $auditor->id,
            'mensaje' => 'Se revisó el diseño del control.',
            'estado_id' => Estado::aprobado()->id,
            'aprobado_por_id' => $auditor->id,
            'data' => ['tipo' => 'cambio', 'origen' => 'reevaluacion_auditor', 'activated_by' => $auditor->name,
                'diff' => ['campos' => ['evaluacion' => ['antes' => null, 'despues' => null]]]],
        ]);

        $this->assertContains($entrada->id, $this->actividad('control', $control->id)->viewData('actualizaciones')->pluck('id')->all());
        $this->assertNotContains($entrada->id, $this->actividad('riesgo', $riesgo->id)->viewData('actualizaciones')->pluck('id')->all());
    }

    /** @return array{0: Control, 1: Riesgo} */
    private function relacionTesteable(): array
    {
        $control = Control::factory()->create([
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
            'mitigacion_default' => 6,
        ]);
        $riesgo = Riesgo::factory()->aprobado()->create(['impacto' => 8, 'probabilidad' => 8, 'area_id' => $this->gerencia->id]);
        $riesgo->controles()->attach($control->id);

        return [$control, $riesgo];
    }
}
