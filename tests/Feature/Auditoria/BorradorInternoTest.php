<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Livewire\Auditoria\PlanAccion\Show\GestionTareas;
use App\Livewire\Auditoria\ValidacionCascadaModal;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Borrador interno (D-044): el flag sólo existe en borrador, saca al elemento del
 * circuito de validación y lo deja como gestión propia del área. Para validar hay
 * que quitarlo; el comité no lo ve.
 */
class BorradorInternoTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private User $comite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $raiz = Area::create(['nombre' => 'Comité']);
        $this->gerencia = Area::create(['nombre' => 'Medio Ambiente', 'area_padre_id' => $raiz->id]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => $raiz->id]);
    }

    /** Un borrador de cada entidad, de la gerencia, con o sin flag. */
    private function entidades(bool $interno): array
    {
        $area = ['area_id' => $this->gerencia->id, 'interno' => $interno];

        return [
            'riesgos' => Riesgo::factory()->borrador()->create($area + ['respuesta' => RespuestaRiesgo::Aceptar]),
            'controles' => Control::factory()->create($area),
            'objetivos' => Objetivo::create($area + ['nombre' => 'Objetivo', 'user_id' => $this->gerente->id]),
            'planes' => PlanAccion::factory()->create($area),
            'tareas' => Tarea::factory()->create($area),
        ];
    }

    #[Test]
    public function un_borrador_interno_no_se_valida(): void
    {
        foreach ($this->entidades(interno: true) as $ruta => $entidad) {
            $this->assertFalse($this->gerente->can('validar', $entidad), $ruta);
            $this->actingAs($this->gerente)
                ->post(route("auditoria.{$ruta}.validar", $entidad))
                ->assertForbidden();
            $this->assertSame('borrador', $entidad->fresh()->estado->nombre, $ruta);
        }
    }

    #[Test]
    public function al_quitar_el_flag_el_borrador_se_valida(): void
    {
        foreach ($this->entidades(interno: false) as $ruta => $entidad) {
            $this->assertTrue($this->gerente->can('validar', $entidad), $ruta);
        }

        $control = Control::factory()->create(['area_id' => $this->gerencia->id, 'interno' => true]);
        $control->update(['interno' => false]);

        $this->actingAs($this->gerente)->post(route('auditoria.controles.validar', $control));

        $this->assertSame('validado', $control->fresh()->estado->nombre);
    }

    #[Test]
    public function la_cascada_marca_como_interno_el_objetivo_que_bloquea(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id, 'respuesta' => RespuestaRiesgo::Aceptar]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->gerencia->id, 'interno' => true]);
        $riesgo->objetivos()->attach($objetivo->id);

        $componente = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar');

        $bloqueante = collect($componente->get('bloqueantes'))->firstWhere('id', $objetivo->id);
        $this->assertTrue($bloqueante['interno']);
        $this->assertFalse($bloqueante['puede_validar']);

        $componente->call('confirmar');

        $this->assertSame('borrador', $riesgo->fresh()->estado->nombre);
        $this->assertSame('borrador', $objetivo->fresh()->estado->nombre);
    }

    #[Test]
    public function la_cascada_no_ofrece_hijos_internos(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id]);
        $interno = Control::factory()->create(['area_id' => $this->gerencia->id, 'interno' => true]);
        $comun = Control::factory()->create(['area_id' => $this->gerencia->id]);
        $riesgo->controles()->attach([$interno->id => ['mitigacion' => 2], $comun->id => ['mitigacion' => 2]]);

        $plan = PlanAccion::factory()->create(['area_id' => $this->gerencia->id]);
        $tareaInterna = Tarea::factory()->create(['area_id' => $this->gerencia->id, 'interno' => true]);
        $plan->tareas()->attach($tareaInterna->id);

        $opcionalesRiesgo = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar')
            ->get('opcionales');
        $opcionalesPlan = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'plan', $plan->id, 'validar')
            ->get('opcionales');

        $this->assertSame([$comun->id], array_column($opcionalesRiesgo, 'id'));
        $this->assertSame([], $opcionalesPlan);
    }

    #[Test]
    public function una_tarea_interna_creada_por_el_gerente_nace_en_borrador(): void
    {
        $plan = PlanAccion::factory()->create(['area_id' => $this->gerencia->id, 'interno' => true]);

        Livewire::actingAs($this->gerente)
            ->test(GestionTareas::class, ['plan' => $plan])
            ->call('abrirFormNuevaTarea')
            ->set('nueva.nombre', 'Tarea interna')
            ->set('nueva.area_id', (string) $this->gerencia->id)
            ->set('nueva.interno', true)
            ->call('guardarNuevaTarea')
            ->assertHasNoErrors();

        $tarea = Tarea::sole();
        $this->assertTrue($tarea->interno);
        $this->assertSame('borrador', $tarea->estado->nombre);
    }

    #[Test]
    public function el_comite_no_ve_elementos_internos(): void
    {
        foreach ($this->entidades(interno: true) as $ruta => $entidad) {
            $this->assertFalse($this->comite->can('view', $entidad), $ruta);
            $this->assertFalse($entidad::visiblePara($this->comite)->whereKey($entidad->id)->exists(), $ruta);
        }
    }
}
