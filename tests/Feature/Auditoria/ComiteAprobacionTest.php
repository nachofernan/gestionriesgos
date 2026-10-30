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
}
