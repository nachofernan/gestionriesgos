<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\AccionActualizacionModal;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\ValidacionCascadaModal;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
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
 * Comité que dictamina cada aprobación (D-039): número y fecha opcionales en la
 * Actualizacion que deja la aprobación de un elemento o de una propuesta.
 */
class ComiteAprobacionTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $comite;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $raiz = Area::create(['nombre' => 'Comité de Riesgo', 'tipo' => TipoArea::Gerencia]);
        $this->gerencia = Area::create(['nombre' => 'Gerencia Legales', 'area_padre_id' => $raiz->id, 'tipo' => TipoArea::Gerencia]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => $raiz->id]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
    }

    private function aprobacionDe($elemento): Actualizacion
    {
        return $elemento->actualizaciones()
            ->where('estado_id', Estado::aprobado()->id)
            ->where('mensaje', 'like', 'Aprobado por%')
            ->latest('id')->firstOrFail();
    }

    private function propuestaValidadaSobreControlAprobado(): Actualizacion
    {
        $control = Control::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::aprobado()->id]);

        return $control->actualizaciones()->create([
            'user_id' => $this->gerente->id,
            'mensaje' => 'Cambio de nombre',
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['nombre' => 'Control renombrado']],
        ]);
    }

    #[Test]
    public function aprobar_en_cascada_guarda_el_comite_en_el_elemento_y_sus_hijos(): void
    {
        $plan = PlanAccion::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);
        $tarea = Tarea::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);
        $plan->tareas()->attach($tarea->id);

        Livewire::actingAs($this->comite)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'plan', $plan->id, 'aprobar')
            ->set('comite.numero', '15/2026')
            ->set('comite.fecha', '2026-10-20')
            ->call('confirmar')
            ->assertHasNoErrors();

        foreach ([$plan, $tarea] as $elemento) {
            $this->assertSame('aprobado', $elemento->fresh()->estado->nombre);
            $aprobacion = $this->aprobacionDe($elemento);
            $this->assertSame('15/2026', $aprobacion->comite_numero);
            $this->assertSame('2026-10-20', $aprobacion->comite_fecha->toDateString());
        }
    }

    #[Test]
    public function aprobar_sin_comite_aprueba_igual_y_lo_deja_en_null(): void
    {
        $plan = PlanAccion::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);

        Livewire::actingAs($this->comite)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'plan', $plan->id, 'aprobar')
            ->call('confirmar')
            ->assertHasNoErrors();

        $this->assertSame('aprobado', $plan->fresh()->estado->nombre);
        $this->assertNull($this->aprobacionDe($plan)->comiteVigente());
    }

    #[Test]
    public function el_comite_con_fecha_futura_o_numero_largo_no_aprueba(): void
    {
        $plan = PlanAccion::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);

        Livewire::actingAs($this->comite)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'plan', $plan->id, 'aprobar')
            ->set('comite.numero', str_repeat('9', 21))
            ->set('comite.fecha', now()->addDay()->toDateString())
            ->call('confirmar')
            ->assertHasErrors(['comite.numero', 'comite.fecha']);

        $this->assertSame('validado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function aprobar_por_la_ruta_guarda_el_comite_si_viene(): void
    {
        $conComite = Control::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);
        $sinComite = Control::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);

        $this->actingAs($this->comite)
            ->post(route('auditoria.controles.aprobar', $conComite), ['comite' => ['numero' => '7', 'fecha' => '2026-09-01']])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->comite)
            ->post(route('auditoria.controles.aprobar', $sinComite))
            ->assertSessionHasNoErrors();

        $this->assertSame(['numero' => '7', 'fecha' => '2026-09-01'], [
            'numero' => $this->aprobacionDe($conComite)->comite_numero,
            'fecha' => $this->aprobacionDe($conComite)->comite_fecha->toDateString(),
        ]);
        $this->assertSame('aprobado', $sinComite->fresh()->estado->nombre);
        $this->assertNull($this->aprobacionDe($sinComite)->comiteVigente());
    }

    #[Test]
    public function aprobar_una_propuesta_guarda_el_comite(): void
    {
        $propuesta = $this->propuestaValidadaSobreControlAprobado();

        Livewire::actingAs($this->comite)
            ->test(GestionActualizaciones::class, ['modelType' => 'control', 'modelId' => $propuesta->actualizable_id, 'variante' => 'timeline'])
            ->dispatch('resolver-actualizacion', id: $propuesta->id, accion: 'aprobar', comite: ['numero' => '12', 'fecha' => '2026-10-01']);

        $propuesta->refresh();
        $this->assertSame('aprobado', $propuesta->estado->nombre);
        $this->assertSame('Control renombrado', $propuesta->actualizable->fresh()->nombre);
        $this->assertSame(['numero' => '12', 'fecha' => '2026-10-01'], [
            'numero' => $propuesta->comite_numero,
            'fecha' => $propuesta->comite_fecha->toDateString(),
        ]);
    }

    #[Test]
    public function aprobar_una_propuesta_desde_pendientes_guarda_el_comite(): void
    {
        $propuesta = $this->propuestaValidadaSobreControlAprobado();

        Livewire::actingAs($this->comite)
            ->test(AccionActualizacionModal::class)
            ->call('abrir', $propuesta->id, 'aprobar')
            ->set('comite.numero', '3')
            ->call('confirmar')
            ->assertHasNoErrors();

        $propuesta->refresh();
        $this->assertSame('aprobado', $propuesta->estado->nombre);
        $this->assertSame('3', $propuesta->comite_numero);
        $this->assertNull($propuesta->comite_fecha);
    }

    private function controlAprobadoPorRuta(array $datos = []): Control
    {
        $control = Control::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);
        $this->actingAs($this->comite)->post(route('auditoria.controles.aprobar', $control), $datos);

        return $control->fresh();
    }

    private function cargarComite($control, ?int $aprobacionId, array $comite, ?User $usuario = null)
    {
        return Livewire::actingAs($usuario ?? $this->comite)
            ->test(GestionActualizaciones::class, ['modelType' => 'control', 'modelId' => $control->id, 'variante' => 'timeline'])
            ->dispatch('registrar-comite', aprobacionId: $aprobacionId, comite: $comite);
    }

    #[Test]
    public function una_segunda_carga_corrige_el_comite_sin_tocar_la_aprobacion(): void
    {
        $control = $this->controlAprobadoPorRuta();
        $aprobacion = $this->aprobacionDe($control);

        $this->cargarComite($control, $aprobacion->id, ['numero' => '10', 'fecha' => '2026-08-01'])->assertHasNoErrors();
        $this->cargarComite($control, $aprobacion->id, ['numero' => '11', 'fecha' => ''])->assertHasNoErrors();

        $aprobacion->refresh();
        $this->assertNull($aprobacion->comite_numero, 'la aprobación original no se reescribe');
        $this->assertCount(2, $aprobacion->registrosComite);
        $this->assertSame(['numero' => '11', 'fecha' => null], $aprobacion->comiteVigente());
        $this->assertSame(['numero' => '11', 'fecha' => null], Actualizacion::comiteVigenteDe($control));
    }

    #[Test]
    public function el_comite_se_carga_desde_la_ficha_de_un_elemento_sin_registro_de_aprobacion(): void
    {
        // Como lo sembrado: aprobado sin "Aprobado por…" en el historial.
        $control = Control::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::aprobado()->id]);
        $this->assertNull(Actualizacion::comiteVigenteDe($control));

        $this->cargarComite($control, null, ['numero' => '1', 'fecha' => '2026-10-01'])
            ->assertHasNoErrors();

        $registro = $control->actualizaciones()->where('data->tipo', 'comite')->sole();
        $this->assertNull($registro->comite_de_id);
        $this->assertSame('1', Actualizacion::comiteVigenteDe($control)['numero']);
    }

    #[Test]
    public function desde_la_ficha_se_carga_sobre_la_ultima_aprobacion(): void
    {
        $control = $this->controlAprobadoPorRuta(['comite' => ['numero' => '4']]);

        $this->cargarComite($control, null, ['numero' => '5'])->assertHasNoErrors();

        $aprobacion = $this->aprobacionDe($control);
        $this->assertSame('4', $aprobacion->comite_numero);
        $this->assertSame($aprobacion->id, $control->actualizaciones()->where('data->tipo', 'comite')->sole()->comite_de_id);
        $this->assertSame('5', Actualizacion::comiteVigenteDe($control)['numero']);
    }

    #[Test]
    public function la_ficha_y_la_actividad_muestran_el_comite(): void
    {
        $control = $this->controlAprobadoPorRuta(['comite' => ['numero' => '4', 'fecha' => '2026-10-15']]);

        $this->actingAs($this->comite)->get(route('auditoria.controles.show', $control))
            ->assertOk()
            ->assertSee('Comité N° 4 · 15/10/2026')
            ->assertSee('Corregir comité');

        Livewire::actingAs($this->comite)
            ->test(GestionActualizaciones::class, ['modelType' => 'control', 'modelId' => $control->id, 'variante' => 'timeline'])
            ->call('abrirActividad')
            ->assertSee('Comité N° 4 · 15/10/2026');

        $this->actingAs($this->gerente)->get(route('auditoria.controles.show', $control))
            ->assertOk()
            ->assertDontSee('Corregir comité');
    }

    #[Test]
    public function cargar_el_comite_exige_numero_o_fecha(): void
    {
        $control = $this->controlAprobadoPorRuta();

        $this->cargarComite($control, null, ['numero' => '', 'fecha' => ''])->assertHasErrors('comite.numero');
        $this->assertSame(0, $control->actualizaciones()->where('data->tipo', 'comite')->count());
    }

    #[Test]
    public function solo_el_comite_carga_el_comite_y_solo_sobre_una_aprobacion(): void
    {
        $control = $this->controlAprobadoPorRuta();
        $aprobacion = $this->aprobacionDe($control);
        $empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);

        // Gerente y empleado del área: 403.
        foreach ([$this->gerente, $empleado] as $usuario) {
            $this->assertFalse($usuario->can('registrarComite', [Actualizacion::class, $control, $aprobacion]));
            $this->cargarComite($control, $aprobacion->id, ['numero' => '1'], $usuario)->assertForbidden();
        }

        // Una propuesta todavía validada no es una aprobación.
        $propuesta = $control->actualizaciones()->create([
            'user_id' => $this->gerente->id, 'mensaje' => 'Cambio', 'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['nombre' => 'Otro']],
        ]);
        $this->cargarComite($control, $propuesta->id, ['numero' => '1'])->assertForbidden();

        // Un registro de comité tampoco: se corrige sobre la aprobación.
        $this->cargarComite($control, $aprobacion->id, ['numero' => '1']);
        $registro = $control->actualizaciones()->where('data->tipo', 'comite')->sole();
        $this->cargarComite($control, $registro->id, ['numero' => '2'])->assertForbidden();

        // La aprobación de otro elemento, desde este: 403.
        $otro = $this->controlAprobadoPorRuta();
        $this->cargarComite($control, $this->aprobacionDe($otro)->id, ['numero' => '1'])->assertForbidden();

        // Un elemento que no está aprobado: 403.
        $validado = Control::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);
        $this->assertFalse($this->comite->can('registrarComite', [Actualizacion::class, $validado]));
        $this->cargarComite($validado, null, ['numero' => '1'])->assertForbidden();

        $this->assertSame(1, Actualizacion::where('data->tipo', 'comite')->count());
    }
}
