<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Eliminación por propuesta de control, objetivo, plan de acción y tarea (D-047,
 * D-058), como la del riesgo: sólo lo aprobado, con motivo obligatorio, y queda
 * borrado cuando lo aprueba el comité. Lo aprobado ya no se borra directo.
 * Propuesto solo, se elimina aunque otros elementos dependan de él: la
 * inconsistencia aparece en esos elementos.
 */
class EliminacionPorPropuestaTest extends TestCase
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

    private function crear(string $prefijo, string $estado = 'aprobado'): Model
    {
        $datos = ['area_id' => $this->gerencia->id, 'user_id' => $this->gerente->id, 'estado_id' => Estado::where('nombre', $estado)->firstOrFail()->id];

        return match ($prefijo) {
            'controles' => Control::factory()->create($datos),
            'objetivos' => Objetivo::create($datos + ['nombre' => 'Objetivo']),
            'planes' => PlanAccion::factory()->create($datos),
            'tareas' => Tarea::factory()->create($datos),
        };
    }

    private function proponer(User $user, string $prefijo, Model $elemento, array $datos = [])
    {
        return $this->actingAs($user)->post(route("auditoria.{$prefijo}.eliminacion", $elemento), $datos + ['observaciones' => 'Ya no existe.']);
    }

    #[Test]
    public function el_gerente_propone_y_queda_borrado_cuando_aprueba_el_comite(): void
    {
        foreach (['controles', 'objetivos', 'planes', 'tareas'] as $prefijo) {
            $elemento = $this->crear($prefijo);

            $this->proponer($this->gerente, $prefijo, $elemento)
                ->assertRedirect(route("auditoria.{$prefijo}.show", $elemento));

            $propuesta = Actualizacion::eliminacionPendienteDe($elemento);
            $this->assertSame('validado', $propuesta->estado->nombre, $prefijo);
            $this->assertSame('aprobado', $elemento->fresh()->estado->nombre, $prefijo);

            $this->actingAs($this->comite)->post(route('auditoria.actualizaciones.aprobar', $propuesta));

            $this->assertSame('borrado', $elemento->fresh()->estado->nombre, $prefijo);
        }
    }

    #[Test]
    public function sin_motivo_no_se_registra(): void
    {
        $control = $this->crear('controles');

        $this->proponer($this->gerente, 'controles', $control, ['observaciones' => ''])
            ->assertSessionHasErrors('observaciones');

        $this->assertNull(Actualizacion::eliminacionPendienteDe($control));
    }

    #[Test]
    public function el_comite_elimina_en_el_acto(): void
    {
        $tarea = $this->crear('tareas');

        $this->proponer($this->comite, 'tareas', $tarea)->assertRedirect(route('auditoria.tareas.index'));

        $this->assertSame('borrado', $tarea->fresh()->estado->nombre);
    }

    #[Test]
    public function quien_no_gestiona_el_area_o_el_auditor_reciben_403(): void
    {
        $otra = Area::create(['nombre' => 'Gerencia B', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->gerente()->create(['area_id' => $otra->id]);
        $auditor = User::factory()->auditor()->create(['area_id' => $otra->id]);

        foreach (['controles', 'objetivos', 'planes', 'tareas'] as $prefijo) {
            $elemento = $this->crear($prefijo);
            $this->proponer($ajeno, $prefijo, $elemento)->assertForbidden();
            $this->proponer($auditor, $prefijo, $elemento)->assertForbidden();
            $this->assertNull(Actualizacion::eliminacionPendienteDe($elemento));
        }
    }

    #[Test]
    public function no_se_propone_dos_veces_ni_sobre_lo_que_no_esta_aprobado(): void
    {
        $objetivo = $this->crear('objetivos');
        $this->proponer($this->gerente, 'objetivos', $objetivo);
        $this->proponer($this->gerente, 'objetivos', $objetivo)->assertForbidden();

        $borrador = $this->crear('objetivos', 'borrador');
        $this->proponer($this->gerente, 'objetivos', $borrador)->assertForbidden();
    }

    #[Test]
    public function lo_aprobado_no_se_borra_directo_y_el_borrador_si(): void
    {
        foreach (['controles', 'objetivos', 'planes', 'tareas'] as $prefijo) {
            $aprobado = $this->crear($prefijo);
            $this->actingAs($this->gerente)->delete(route("auditoria.{$prefijo}.destroy", $aprobado))->assertForbidden();
            $this->assertNotSoftDeleted($aprobado);

            $borrador = $this->crear($prefijo, 'borrador');
            $this->actingAs($this->gerente)->delete(route("auditoria.{$prefijo}.destroy", $borrador))->assertRedirect();
            $this->assertSoftDeleted($borrador);
        }
    }

    #[Test]
    public function un_control_compartido_se_elimina_por_propuesta_propia(): void
    {
        $control = $this->crear('controles');
        foreach (range(1, 2) as $i) {
            Riesgo::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::aprobado()->id])
                ->controles()->attach($control->id);
        }

        $this->proponer($this->gerente, 'controles', $control);
        $this->actingAs($this->comite)->post(route('auditoria.actualizaciones.aprobar', Actualizacion::eliminacionPendienteDe($control)));

        $this->assertSame('borrado', $control->fresh()->estado->nombre);
    }

    #[Test]
    public function un_plan_que_es_el_unico_de_un_riesgo_se_elimina_por_propuesta_propia(): void
    {
        $plan = $this->crear('planes');
        $riesgo = Riesgo::factory()->create([
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
            'respuesta' => RespuestaRiesgo::Mitigar,
        ]);
        $riesgo->planesAccion()->attach($plan->id);

        $this->proponer($this->gerente, 'planes', $plan);
        $this->actingAs($this->comite)->post(route('auditoria.actualizaciones.aprobar', Actualizacion::eliminacionPendienteDe($plan)));

        $this->assertSame('borrado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function la_ficha_muestra_el_modal_solo_a_quien_puede_proponer(): void
    {
        $plan = $this->crear('planes');
        $auditor = User::factory()->auditor()->create(['area_id' => $this->gerencia->id]);

        $this->actingAs($this->gerente)->get(route('auditoria.planes.show', $plan))
            ->assertOk()->assertSee(route('auditoria.planes.eliminacion', $plan), false);
        $this->actingAs($auditor)->get(route('auditoria.planes.show', $plan))
            ->assertOk()->assertDontSee(route('auditoria.planes.eliminacion', $plan), false);
    }
}
