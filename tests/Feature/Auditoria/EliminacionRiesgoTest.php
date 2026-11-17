<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Eliminación de un riesgo por propuesta (D-047): sólo lo aprobado, con
 * observaciones obligatorias y adjunto opcional. Queda borrado recién cuando lo
 * aprueba el comité. Los controles y planes de una sola asociación se pueden
 * marcar en el mismo pedido y llevan su propia propuesta, independiente.
 */
class EliminacionRiesgoTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private User $comite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->gerente()->create(['area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->comite()->create(['area_id' => null]);
    }

    private function riesgo(string $estado = 'aprobado', ?Area $area = null): Riesgo
    {
        return Riesgo::factory()->create([
            'area_id' => ($area ?? $this->gerencia)->id,
            'estado_id' => Estado::where('nombre', $estado)->firstOrFail()->id,
        ]);
    }

    private function control(): Control
    {
        return Control::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::aprobado()->id]);
    }

    private function plan(): PlanAccion
    {
        return PlanAccion::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::aprobado()->id]);
    }

    private function proponer(User $user, Riesgo $riesgo, array $datos = [])
    {
        return $this->actingAs($user)->post(route('auditoria.riesgos.eliminacion', $riesgo), $datos + ['observaciones' => 'Ya no aplica a la operación.']);
    }

    private function aprobar(Actualizacion $propuesta)
    {
        return $this->actingAs($this->comite)->post(route('auditoria.actualizaciones.aprobar', $propuesta));
    }

    private function propuestaDe($entidad): Actualizacion
    {
        return Actualizacion::eliminacionPendienteDe($entidad);
    }

    #[Test]
    public function un_gerente_propone_eliminar_y_el_riesgo_queda_borrado_cuando_aprueba_el_comite(): void
    {
        Storage::fake('public');
        $riesgo = $this->riesgo();

        $this->proponer($this->gerente, $riesgo, ['adjunto' => UploadedFile::fake()->create('acta.pdf', 20, 'application/pdf')])
            ->assertRedirect(route('auditoria.riesgos.show', $riesgo));

        $propuesta = $this->propuestaDe($riesgo);
        $this->assertSame('validado', $propuesta->estado->nombre);
        $this->assertSame('Ya no aplica a la operación.', $propuesta->mensaje);
        $this->assertCount(1, $propuesta->getMedia('adjuntos'));
        // Hasta que decide el comité, el riesgo sigue vigente.
        $this->assertSame('aprobado', $riesgo->fresh()->estado->nombre);

        $this->aprobar($propuesta);

        $this->assertSame('borrado', $riesgo->fresh()->estado->nombre);
        $this->assertSame('aprobado', $propuesta->fresh()->estado->nombre);
    }

    #[Test]
    public function la_propuesta_del_empleado_la_valida_su_gerente_y_la_aprueba_el_comite(): void
    {
        $empleado = User::factory()->empleado()->create(['area_id' => $this->gerencia->id]);
        $riesgo = $this->riesgo();

        $this->proponer($empleado, $riesgo);
        $propuesta = $this->propuestaDe($riesgo);
        $this->assertSame('borrador', $propuesta->estado->nombre);

        $this->actingAs($this->gerente)->post(route('auditoria.actualizaciones.validar', $propuesta));
        $this->assertSame('validado', $propuesta->fresh()->estado->nombre);
        $this->assertSame('aprobado', $riesgo->fresh()->estado->nombre);

        $this->aprobar($propuesta->fresh());
        $this->assertSame('borrado', $riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function las_observaciones_son_obligatorias(): void
    {
        $riesgo = $this->riesgo();

        $this->proponer($this->gerente, $riesgo, ['observaciones' => ''])->assertSessionHasErrors('observaciones');

        $this->assertNull(Actualizacion::eliminacionPendienteDe($riesgo));
    }

    #[Test]
    public function solo_se_ofrecen_los_controles_y_planes_de_una_sola_asociacion(): void
    {
        // El ejemplo de la reunión: el control B también mitiga otro riesgo, el
        // control C y el plan D sólo están en A. Se marca sólo C.
        $a = $this->riesgo();
        $otro = $this->riesgo();
        [$b, $c] = [$this->control(), $this->control()];
        $d = $this->plan();
        $a->controles()->attach([$b->id, $c->id]);
        $otro->controles()->attach($b->id);
        $a->planesAccion()->attach($d->id);

        $ofrecidos = $a->dependientesEliminables($this->gerente);
        $seleccionables = fn ($tipo) => collect($ofrecidos[$tipo])->where('seleccionable', true)->pluck('id')->all();
        $this->assertSame([$c->id], $seleccionables('controles'));
        $this->assertSame([$d->id], $seleccionables('planes'));
        $this->assertSame('También mitiga otros riesgos.', collect($ofrecidos['controles'])->firstWhere('id', $b->id)['motivo']);

        $this->proponer($this->gerente, $a, ['controles' => [$c->id]]);

        $this->assertNotNull($this->propuestaDe($a));
        $this->assertNotNull($this->propuestaDe($c));
        $this->assertNull(Actualizacion::eliminacionPendienteDe($b));
        $this->assertNull(Actualizacion::eliminacionPendienteDe($d));
        $this->assertSame($a->id, $this->propuestaDe($c)->data['junto_con_riesgo_id']);
    }

    #[Test]
    public function no_se_puede_colar_un_control_compartido(): void
    {
        $a = $this->riesgo();
        $b = $this->control();
        $a->controles()->attach($b->id);
        $this->riesgo()->controles()->attach($b->id);

        $this->proponer($this->gerente, $a, ['controles' => [$b->id]])->assertSessionHasErrors('controles');

        $this->assertNull(Actualizacion::eliminacionPendienteDe($a));
        $this->assertNull(Actualizacion::eliminacionPendienteDe($b));
    }

    #[Test]
    public function las_propuestas_se_resuelven_por_separado(): void
    {
        $a = $this->riesgo();
        $c = $this->control();
        $a->controles()->attach($c->id);

        $this->proponer($this->gerente, $a, ['controles' => [$c->id]]);

        $this->actingAs($this->comite)->post(route('auditoria.actualizaciones.rechazar', $this->propuestaDe($a)), ['motivo' => 'El riesgo sigue vigente.']);
        $this->aprobar($this->propuestaDe($c));

        $this->assertSame('aprobado', $a->fresh()->estado->nombre);
        $this->assertSame('borrado', $c->fresh()->estado->nombre);
    }

    #[Test]
    public function al_eliminar_el_plan_caen_sus_tareas_que_no_estan_en_otro_plan(): void
    {
        $a = $this->riesgo();
        $d = $this->plan();
        $otroPlan = $this->plan();
        $propia = Tarea::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::aprobado()->id]);
        $compartida = Tarea::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::aprobado()->id]);
        $a->planesAccion()->attach($d->id);
        $d->tareas()->attach([$propia->id, $compartida->id]);
        $otroPlan->tareas()->attach($compartida->id);

        $this->proponer($this->gerente, $a, ['planes' => [$d->id]]);
        $this->aprobar($this->propuestaDe($a));
        $this->aprobar($this->propuestaDe($d));

        $this->assertSame('borrado', $d->fresh()->estado->nombre);
        $this->assertSame('borrado', $propia->fresh()->estado->nombre);
        $this->assertSame('aprobado', $compartida->fresh()->estado->nombre);
    }

    #[Test]
    public function un_control_asociado_despues_a_otro_riesgo_no_se_elimina_al_aprobar(): void
    {
        $a = $this->riesgo();
        $c = $this->control();
        $a->controles()->attach($c->id);
        $this->proponer($this->gerente, $a, ['controles' => [$c->id]]);

        // Mientras espera al comité, alguien lo asocia a otro riesgo.
        $this->riesgo()->controles()->attach($c->id);

        $this->aprobar($this->propuestaDe($c))->assertSessionHasErrors('eliminacion');

        $this->assertSame('aprobado', $c->fresh()->estado->nombre);
        $this->assertSame('validado', $this->propuestaDe($c)->estado->nombre);
    }

    #[Test]
    public function el_comite_elimina_en_el_acto(): void
    {
        $riesgo = $this->riesgo();

        $this->proponer($this->comite, $riesgo)->assertRedirect(route('auditoria.riesgos.index'));

        $this->assertSame('borrado', $riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function solo_se_propone_eliminar_un_riesgo_aprobado(): void
    {
        foreach (['borrador', 'validado'] as $estado) {
            $this->proponer($this->gerente, $this->riesgo($estado))->assertForbidden();
        }
    }

    #[Test]
    public function nadie_de_otra_gerencia_ni_el_auditor_propone_eliminar(): void
    {
        $otra = Area::create(['nombre' => 'Gerencia B', 'tipo' => TipoArea::Gerencia]);
        $gerenteAjeno = User::factory()->gerente()->create(['area_id' => $otra->id]);
        $auditor = User::factory()->auditor()->create(['area_id' => $otra->id]);
        $riesgo = $this->riesgo();

        $this->proponer($gerenteAjeno, $riesgo)->assertForbidden();
        $this->proponer($auditor, $riesgo)->assertForbidden();
        $this->assertNull(Actualizacion::eliminacionPendienteDe($riesgo));
    }

    #[Test]
    public function no_se_propone_dos_veces_ni_queda_el_borrado_directo(): void
    {
        $riesgo = $this->riesgo();
        $this->proponer($this->gerente, $riesgo);

        $this->proponer($this->gerente, $riesgo)->assertForbidden();
        $this->actingAs($this->gerente)->delete('/auditoria/riesgos/'.$riesgo->id)->assertStatus(405);
        $this->assertNull($riesgo->fresh()->deleted_at);
    }

    #[Test]
    public function la_ficha_ofrece_proponer_la_eliminacion_solo_a_quien_puede(): void
    {
        $riesgo = $this->riesgo();

        $this->actingAs($this->gerente)->get(route('auditoria.riesgos.show', $riesgo))->assertSee('Proponer eliminación');
        $empleadoAjeno = User::factory()->empleado()->create(['area_id' => Area::create(['nombre' => 'Otra'])->id]);
        $this->actingAs($empleadoAjeno)->get(route('auditoria.riesgos.show', $riesgo))->assertDontSee('Proponer eliminación');
    }
}
