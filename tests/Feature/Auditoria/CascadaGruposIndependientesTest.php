<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Livewire\Auditoria\ValidacionCascadaModal;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use App\Services\Auditoria\ValidacionMasivaService;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre dos correcciones relacionadas: (1) el Objetivo asociado a un riesgo
 * ahora exige estado validado/aprobado, igual que el Plan (antes sólo exigía
 * que existiera — Riesgo::motivosBloqueoValidacion()/motivosBloqueoAprobacion());
 * (2) el modal de validación en cascada trataba TODOS los bloqueantes (Objetivo
 * + Plan) como un único pool donde "seleccionar cualquiera" alcanzaba para
 * confirmar. Eso permitía esquivar el objetivo con sólo dejar tildado el plan
 * (o viceversa): el riesgo se aprobaba sin que el objetivo pasara por su propio
 * prerequisito. Ahora cada grupo (tipo de bloqueante) exige su propia selección
 * — ValidacionCascadaModal::toggleSeleccion()/confirmar().
 */
class CascadaGruposIndependientesTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $comite;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->area = Area::create(['nombre' => 'Gerencia', 'area_padre_id' => null]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->area->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
    }

    /** Riesgo mitigar con un objetivo y un plan, ambos en borrador (nada validado todavía). */
    private function riesgoMitigarConObjetivoYPlanEnBorrador(): array
    {
        $riesgo = Riesgo::factory()->borrador()->create([
            'area_id' => $this->area->id,
            'respuesta' => RespuestaRiesgo::Mitigar,
        ]);
        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo del riesgo',
            'area_id' => $this->area->id,
            'user_id' => $this->gerente->id,
        ]); // borrador por defecto
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id]); // borrador por defecto
        $riesgo->objetivos()->attach($objetivo->id);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        return [$riesgo, $objetivo, $plan];
    }

    // -------------------------------------------------------
    // Regla del modelo: Objetivo también exige estado, no sólo existencia
    // -------------------------------------------------------

    #[Test]
    public function un_objetivo_en_borrador_no_alcanza_para_validar_el_riesgo_aunque_exista()
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->area->id, 'respuesta' => RespuestaRiesgo::Aceptar]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->area->id]); // borrador
        $riesgo->objetivos()->attach($objetivo->id);

        $motivos = $riesgo->motivosBloqueoValidacion();

        $this->assertNotEmpty($motivos);
        $this->assertStringContainsString('objetivo asociado debe estar validado', $motivos[0]);
    }

    #[Test]
    public function un_objetivo_validado_pero_no_aprobado_no_alcanza_para_aprobar_el_riesgo()
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->area->id, 'respuesta' => RespuestaRiesgo::Aceptar]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->objetivos()->attach($objetivo->id);

        $motivos = $riesgo->motivosBloqueoAprobacion();

        $this->assertNotEmpty($motivos);
        $this->assertStringContainsString('objetivo asociado debe estar aprobado', $motivos[0]);
    }

    // -------------------------------------------------------
    // El bug reportado: esquivar un grupo dejando seleccionado sólo el otro
    // -------------------------------------------------------

    #[Test]
    public function no_se_puede_deseleccionar_el_unico_objetivo_del_grupo_aunque_el_plan_siga_seleccionado()
    {
        [$riesgo] = $this->riesgoMitigarConObjetivoYPlanEnBorrador();

        $componente = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar');

        $keyObjetivo = collect($componente->get('bloqueantes'))->firstWhere('tipo', 'objetivo');
        $keyObjetivo = $keyObjetivo['tipo'].':'.$keyObjetivo['id'];

        $componente->call('toggleSeleccion', $keyObjetivo)
            ->assertSet('error', fn ($error) => $error !== null && str_contains($error, 'objetivo'))
            ->assertSet("seleccionados.{$keyObjetivo}", true); // no se deselecciona
    }

    #[Test]
    public function confirmar_bloquea_si_se_fuerza_a_dejar_un_grupo_sin_seleccion_aunque_otro_grupo_este_completo()
    {
        [$riesgo] = $this->riesgoMitigarConObjetivoYPlanEnBorrador();

        $componente = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar');

        // Fuerza el estado inválido saltando toggleSeleccion (que ya lo impide),
        // para probar que confirmar() es la última línea de defensa, no sólo la UI.
        $seleccionados = $componente->get('seleccionados');
        foreach ($componente->get('bloqueantes') as $item) {
            if ($item['tipo'] === 'objetivo') {
                $seleccionados[$item['tipo'].':'.$item['id']] = false;
            }
        }

        $componente->set('seleccionados', $seleccionados)
            ->call('confirmar')
            ->assertSet('error', fn ($error) => $error !== null && str_contains($error, 'objetivo'));

        $this->assertEquals('borrador', $riesgo->fresh()->estado->nombre);
    }

    #[Test]
    public function validar_desde_la_cascada_con_ambos_grupos_seleccionados_valida_los_tres()
    {
        [$riesgo, $objetivo, $plan] = $this->riesgoMitigarConObjetivoYPlanEnBorrador();

        Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar')
            ->call('confirmar')
            ->assertSet('error', null);

        $this->assertEquals('validado', $riesgo->fresh()->estado->nombre);
        $this->assertEquals('validado', $objetivo->fresh()->estado->nombre);
        $this->assertEquals('validado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function la_cascada_de_validacion_del_riesgo_agrupa_objetivo_y_plan_como_bloqueantes_independientes()
    {
        [$riesgo] = $this->riesgoMitigarConObjetivoYPlanEnBorrador();

        $analisis = app(ValidacionMasivaService::class)->analizar($riesgo, 'validar', $this->gerente);

        $tipos = collect($analisis['bloqueantes'])->pluck('tipo')->unique()->values();
        $this->assertEqualsCanonicalizing(['objetivo', 'plan'], $tipos->all());
    }

    // -------------------------------------------------------
    // Mismas garantías, pero para "aprobar" (nunca se habían probado: los 6
    // tests de arriba sólo cubrían "validar"). Reproduce el reporte del usuario:
    // riesgo validado con objetivo y plan YA aprobados, más el caso donde sólo
    // uno de los dos necesita promoción todavía.
    // -------------------------------------------------------

    #[Test]
    public function si_objetivo_y_plan_ya_estan_aprobados_la_cascada_de_aprobar_no_ofrece_bloqueantes()
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->area->id, 'respuesta' => RespuestaRiesgo::Mitigar]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $riesgo->objetivos()->attach($objetivo->id);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $analisis = app(ValidacionMasivaService::class)->analizar($riesgo, 'aprobar', $this->comite);

        // Nada que exigir: ambos ya cumplen el estado requerido para aprobar.
        $this->assertEmpty($analisis['bloqueantes']);

        Livewire::actingAs($this->comite)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'aprobar')
            ->call('confirmar')
            ->assertSet('error', null);

        $this->assertEquals('aprobado', $riesgo->fresh()->estado->nombre);
        // Sin cambios: ya estaban aprobados antes de esta acción, no los tocó.
        $this->assertEquals('aprobado', $objetivo->fresh()->estado->nombre);
        $this->assertEquals('aprobado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function si_solo_el_plan_necesita_promocion_la_cascada_de_aprobar_no_ofrece_el_objetivo_ya_aprobado()
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->area->id, 'respuesta' => RespuestaRiesgo::Mitigar]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->objetivos()->attach($objetivo->id);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $analisis = app(ValidacionMasivaService::class)->analizar($riesgo, 'aprobar', $this->comite);

        $tipos = collect($analisis['bloqueantes'])->pluck('tipo')->unique()->values();
        $this->assertEquals(['plan'], $tipos->all());
    }

    #[Test]
    public function no_se_puede_deseleccionar_el_unico_plan_bloqueante_al_aprobar_aunque_el_objetivo_ya_este_aprobado()
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->area->id, 'respuesta' => RespuestaRiesgo::Mitigar]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->objetivos()->attach($objetivo->id);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $componente = Livewire::actingAs($this->comite)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'aprobar');

        $keyPlan = collect($componente->get('bloqueantes'))->firstWhere('tipo', 'plan');
        $keyPlan = $keyPlan['tipo'].':'.$keyPlan['id'];

        // toggleSeleccion debe rechazar la deselección (único bloqueante de su grupo).
        $componente->call('toggleSeleccion', $keyPlan)
            ->assertSet('error', fn ($error) => $error !== null && str_contains($error, 'plan'))
            ->assertSet("seleccionados.{$keyPlan}", true);

        // Y si se fuerza igual (saltando toggleSeleccion), confirmar() lo frena.
        $seleccionados = $componente->get('seleccionados');
        $seleccionados[$keyPlan] = false;
        $componente->set('seleccionados', $seleccionados)
            ->call('confirmar')
            ->assertSet('error', fn ($error) => $error !== null && str_contains($error, 'plan'));

        $this->assertEquals('validado', $riesgo->fresh()->estado->nombre);
        $this->assertEquals('validado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function aprobar_el_riesgo_seleccionando_el_plan_bloqueante_aprueba_ambos_sin_tocar_el_objetivo_ya_aprobado()
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->area->id, 'respuesta' => RespuestaRiesgo::Mitigar]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->area->id, 'estado_id' => Estado::aprobado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->objetivos()->attach($objetivo->id);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        Livewire::actingAs($this->comite)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'aprobar')
            ->call('confirmar')
            ->assertSet('error', null);

        $this->assertEquals('aprobado', $riesgo->fresh()->estado->nombre);
        $this->assertEquals('aprobado', $plan->fresh()->estado->nombre);
        $this->assertEquals('aprobado', $objetivo->fresh()->estado->nombre); // ya lo estaba
    }

    /**
     * Reproduce literalmente el reporte del usuario: riesgo validado con objetivo
     * Y plan todavía en validado (ninguno de los dos aprobado aún) — el caso de
     * DOS grupos bloqueantes simultáneos al aprobar, nunca antes probado (los
     * tests previos de "aprobar" sólo tenían UN grupo bloqueante a la vez).
     */
    #[Test]
    public function con_objetivo_y_plan_ambos_validados_no_se_puede_esquivar_ninguno_al_aprobar()
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->area->id, 'respuesta' => RespuestaRiesgo::Mitigar]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->objetivos()->attach($objetivo->id);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $componente = Livewire::actingAs($this->comite)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'aprobar');

        $tipos = collect($componente->get('bloqueantes'))->pluck('tipo')->unique()->values();
        $this->assertEqualsCanonicalizing(['objetivo', 'plan'], $tipos->all());

        $keyObjetivo = collect($componente->get('bloqueantes'))->firstWhere('tipo', 'objetivo');
        $keyObjetivo = $keyObjetivo['tipo'].':'.$keyObjetivo['id'];

        // Deseleccionar el objetivo vía toggleSeleccion (como haría un click real) debe rechazarse.
        $componente->call('toggleSeleccion', $keyObjetivo)
            ->assertSet('error', fn ($error) => $error !== null && str_contains($error, 'objetivo'))
            ->assertSet("seleccionados.{$keyObjetivo}", true);

        // Y si igual se fuerza (bypasseando toggleSeleccion), confirmar() debe frenarlo.
        $seleccionados = $componente->get('seleccionados');
        $seleccionados[$keyObjetivo] = false;
        $componente->set('seleccionados', $seleccionados)
            ->call('confirmar')
            ->assertSet('error', fn ($error) => $error !== null && str_contains($error, 'objetivo'));

        $this->assertEquals('validado', $riesgo->fresh()->estado->nombre);
        $this->assertEquals('validado', $objetivo->fresh()->estado->nombre);
        $this->assertEquals('validado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function con_objetivo_y_plan_ambos_validados_aprobar_con_todo_seleccionado_aprueba_los_tres()
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->area->id, 'respuesta' => RespuestaRiesgo::Mitigar]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $plan = PlanAccion::factory()->create(['area_id' => $this->area->id, 'estado_id' => Estado::validado()->id]);
        $riesgo->objetivos()->attach($objetivo->id);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        Livewire::actingAs($this->comite)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'aprobar')
            ->call('confirmar')
            ->assertSet('error', null);

        $this->assertEquals('aprobado', $riesgo->fresh()->estado->nombre);
        $this->assertEquals('aprobado', $objetivo->fresh()->estado->nombre);
        $this->assertEquals('aprobado', $plan->fresh()->estado->nombre);
    }

    // -------------------------------------------------------
    // Bug de renderizado reportado por el usuario: el checkbox quedaba
    // visualmente destildado (el navegador invierte `checked` al hacer clic,
    // antes de que responda el servidor) mientras el servidor lo seguía
    // considerando seleccionado, porque nada forzaba a Livewire a recrear el
    // nodo cuando el toggle se RECHAZABA (⟹ $seleccionados no cambiaba, así
    // que una key basada en ese valor tampoco cambiaba). El fix es un
    // contador $version que se incrementa en cada intento de clic, se acepte
    // o se rechace, usado como sufijo del wire:key del checkbox.
    // -------------------------------------------------------

    #[Test]
    public function version_se_incrementa_tanto_si_el_toggle_se_acepta_como_si_se_rechaza()
    {
        [$riesgo] = $this->riesgoMitigarConObjetivoYPlanEnBorrador();

        $componente = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar')
            ->assertSet('version', 0);

        $keyObjetivo = collect($componente->get('bloqueantes'))->firstWhere('tipo', 'objetivo');
        $keyObjetivo = $keyObjetivo['tipo'].':'.$keyObjetivo['id'];

        // Rechazado (único item de su grupo): version sube igual.
        $componente->call('toggleSeleccion', $keyObjetivo)
            ->assertSet('version', 1)
            ->assertSet("seleccionados.{$keyObjetivo}", true);

        // Un control opcional si existiera se aceptaría sin problema; acá repetimos
        // sobre el mismo objetivo (sigue rechazado) para confirmar que cada intento cuenta.
        $componente->call('toggleSeleccion', $keyObjetivo)
            ->assertSet('version', 2);
    }

    #[Test]
    public function puede_confirmar_es_false_mientras_falte_seleccion_en_algun_grupo()
    {
        [$riesgo] = $this->riesgoMitigarConObjetivoYPlanEnBorrador();

        $componente = Livewire::actingAs($this->gerente)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'riesgo', $riesgo->id, 'validar');

        $this->assertTrue($componente->instance()->puedeConfirmar()); // pre-seleccionado por defecto

        $keyObjetivo = collect($componente->get('bloqueantes'))->firstWhere('tipo', 'objetivo');
        $keyObjetivo = $keyObjetivo['tipo'].':'.$keyObjetivo['id'];

        // Se fuerza el hueco saltando toggleSeleccion (que ya lo impide) para
        // aislar puedeConfirmar() de esa otra protección.
        $seleccionados = $componente->get('seleccionados');
        $seleccionados[$keyObjetivo] = false;
        $componente->set('seleccionados', $seleccionados);

        $this->assertFalse($componente->instance()->puedeConfirmar());
    }
}
