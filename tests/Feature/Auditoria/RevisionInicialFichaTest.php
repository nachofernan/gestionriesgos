<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\RevisionInicial\PendientesFicha;
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
 * Card "Para la revisión inicial" de las fichas (D-048): muestra lo que dice
 * el detector, ofrece Recalcular sólo a quien puede y, firmado, queda en una línea.
 */
class RevisionInicialFichaTest extends TestCase
{
    use RefreshDatabase;

    private Area $sistemas;

    private User $gerente;

    private User $comite;

    private User $coordinador;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
        config(['auditoria.revision_inicial.activa' => true]);

        $raiz = Area::create(['nombre' => 'Comité de Riesgo', 'tipo' => TipoArea::Gerencia]);
        $this->sistemas = Area::create(['nombre' => 'Gerencia Sistemas', 'tipo' => TipoArea::Gerencia, 'area_padre_id' => $raiz->id]);

        $this->gerente = User::factory()->gerente()->create(['area_id' => $this->sistemas->id]);
        $this->comite = User::factory()->comite()->create(['area_id' => $raiz->id]);
        $coordinacion = Area::create(['nombre' => 'Coordinación Infraestructura', 'area_padre_id' => $this->sistemas->id]);
        $this->coordinador = User::factory()->empleado()->create(['area_id' => $coordinacion->id]);

        // Control sin inconsistencias: se firma a secas.
        $this->control = Control::factory()->valorado()->create([
            'area_id' => $coordinacion->id,
            'user_id' => $this->coordinador->id,
            'estado_id' => Estado::aprobado()->id,
            'mitigacion_default' => 3,
        ]);
        $this->control->riesgos()->attach(Riesgo::factory()->aprobado()->create(['area_id' => $this->sistemas->id])->id);
    }

    /** Riesgo aprobado como entra de la carga inicial: sin evaluación ni objetivos. */
    private function riesgoDeLaCarga(): Riesgo
    {
        return Riesgo::factory()->aprobado()->create([
            'area_id' => $this->sistemas->id,
            'user_id' => $this->gerente->id,
            'evaluacion' => null,
        ]);
    }

    private function card(User $user, string $tipo, int $id)
    {
        return Livewire::actingAs($user)->test(PendientesFicha::class, ['tipo' => $tipo, 'modeloId' => $id]);
    }

    #[Test]
    public function la_ficha_del_riesgo_muestra_lo_que_falta_para_la_revision(): void
    {
        $riesgo = $this->riesgoDeLaCarga();

        $this->card($this->gerente, 'riesgo', $riesgo->id)
            ->assertSee('Para la revisión inicial')
            ->assertSee('No tiene evaluación vigente')
            ->assertSee('No tiene objetivos asociados.')
            ->assertSee('href="#objetivos"', false)
            ->assertSee('#objetivos:not([data-editando])', false)
            ->assertSee('#valor [data-revision-recalcular]', false);
    }

    #[Test]
    public function el_boton_recalcular_aparece_solo_para_quien_puede_recalcular(): void
    {
        $riesgo = $this->riesgoDeLaCarga();
        $ruta = route('auditoria.riesgos.recalcular', $riesgo);

        $this->card($this->gerente, 'riesgo', $riesgo->id)->assertSee($ruta);

        $this->card($this->comite, 'riesgo', $riesgo->id)
            ->assertSee('No tiene evaluación vigente')
            ->assertDontSee($ruta);
    }

    #[Test]
    public function un_elemento_firmado_muestra_solo_la_linea_de_firma(): void
    {
        $riesgo = $this->riesgoDeLaCarga();
        RevisionInicial::create([
            'revisable_type' => $riesgo->getMorphClass(),
            'revisable_id' => $riesgo->id,
            'gerencia_id' => $this->sistemas->id,
            'firmado_por_id' => $this->gerente->id,
            'firmado_en' => now(),
        ]);

        $this->card($this->gerente, 'riesgo', $riesgo->id)
            ->assertSee('Revisión inicial firmada por')
            ->assertSee($this->gerente->name)
            ->assertDontSee('No tiene evaluación vigente')
            ->assertDontSee('data-revision-recalcular', false);
    }

    #[Test]
    public function con_la_revision_apagada_la_ficha_no_muestra_nada(): void
    {
        config(['auditoria.revision_inicial.activa' => false]);
        $riesgo = $this->riesgoDeLaCarga();

        $this->card($this->gerente, 'riesgo', $riesgo->id)
            ->assertDontSee('Para la revisión inicial')
            ->assertDontSee('No tiene evaluación vigente');
    }

    #[Test]
    public function la_card_aparece_en_las_cinco_fichas(): void
    {
        $atributos = ['area_id' => $this->sistemas->id, 'user_id' => $this->gerente->id];
        $fichas = [
            'auditoria.riesgos.show' => $this->riesgoDeLaCarga(),
            'auditoria.controles.show' => Control::factory()->valorado()->create($atributos),
            'auditoria.objetivos.show' => Objetivo::create(['nombre' => 'Objetivo', ...$atributos]),
            'auditoria.planes.show' => PlanAccion::factory()->create($atributos),
            'auditoria.tareas.show' => Tarea::factory()->create($atributos),
        ];

        foreach ($fichas as $ruta => $elemento) {
            $this->actingAs($this->gerente)->get(route($ruta, $elemento))
                ->assertOk()
                ->assertSeeLivewire(PendientesFicha::class);
        }
    }

    #[Test]
    public function desde_la_ficha_el_gerente_firma(): void
    {
        $this->card($this->gerente, 'control', $this->control->id)
            ->assertSee('Firmar')
            ->call('firmar')
            ->assertHasNoErrors()
            ->assertSee('Revisión inicial firmada por');

        $this->assertEquals($this->gerente->id, RevisionInicial::where('revisable_id', $this->control->id)->value('firmado_por_id'));
    }

    #[Test]
    public function desde_la_ficha_el_gerente_valida_y_firma(): void
    {
        $riesgo = Riesgo::factory()->aprobado()->create(['area_id' => $this->sistemas->id, 'impacto' => 5, 'probabilidad' => 5, 'evaluacion' => ['metodo' => 'clasica', 'respuestas' => []]]);
        $riesgo->controles()->attach($this->control->id);
        $objetivo = Objetivo::create(['nombre' => 'Continuidad operativa', 'area_id' => $this->sistemas->id, 'user_id' => $this->gerente->id]);
        $propuesta = $riesgo->actualizaciones()->create([
            'user_id' => $this->coordinador->id,
            'mensaje' => 'Corrección de la carga',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'cambio', 'relaciones' => ['objetivos' => ['agregar' => [$objetivo->id => []]]]],
        ]);

        $this->card($this->gerente, 'riesgo', $riesgo->id)
            ->assertSee('Validar y firmar (1 cambio)')
            ->call('firmar')
            ->assertHasNoErrors()
            ->assertDispatched('riesgo-actualizado');

        $this->assertEquals('validado', $propuesta->fresh()->estado->nombre);
        $this->assertEquals($this->gerente->id, RevisionInicial::where('revisable_id', $riesgo->id)->value('firmado_por_id'));
    }

    #[Test]
    public function desde_la_ficha_no_se_firma_con_errores(): void
    {
        $riesgo = $this->riesgoDeLaCarga();

        $this->card($this->gerente, 'riesgo', $riesgo->id)
            // Riesgo aprobado: la otra salida es proponer el borrado (D-058).
            ->assertSee('Arreglá los errores o proponé eliminarlo para poder firmar.')
            ->assertSee('Proponer eliminación')
            ->call('firmar')
            ->assertHasErrors('revision');

        $this->assertDatabaseCount('revisiones_iniciales', 0);
    }

    #[Test]
    public function desde_la_ficha_el_coordinador_marca_revisado(): void
    {
        $this->card($this->coordinador, 'control', $this->control->id)
            ->assertSee('Marcar revisado')
            ->assertDontSee("wire:click=\"abrir('firmar')\"", false)
            ->call('marcarRevisado')
            ->assertHasNoErrors()
            ->assertSee('Revisado por');

        $revision = RevisionInicial::where('revisable_id', $this->control->id)->firstOrFail();
        $this->assertEquals($this->coordinador->id, $revision->revisado_por_id);
        $this->assertNull($revision->firmado_en);
    }

    #[Test]
    public function quien_no_firma_recibe_403_desde_la_ficha(): void
    {
        $this->card($this->comite, 'control', $this->control->id)
            ->assertDontSee('Marcar revisado')
            ->call('firmar')
            ->assertForbidden();
        $this->card($this->comite, 'control', $this->control->id)->call('marcarRevisado')->assertForbidden();
        $this->card($this->coordinador, 'control', $this->control->id)->call('firmar')->assertForbidden();
        $this->card($this->gerente, 'control', $this->control->id)->call('marcarRevisado')->assertForbidden();

        $this->assertDatabaseCount('revisiones_iniciales', 0);
    }
}
