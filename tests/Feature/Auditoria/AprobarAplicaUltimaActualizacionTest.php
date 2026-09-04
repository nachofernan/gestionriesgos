<?php

namespace Tests\Feature\Auditoria;

use App\Livewire\Auditoria\ValidacionCascadaModal;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
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
 * Regresión: al aprobar una entidad con varias Actualizaciones "validado"
 * acumuladas, tiene que quedar aplicado el valor de la más reciente, no el
 * de la más vieja. El bug: `actualizaciones()` trae el historial ordenado
 * `latest()` (para mostrarlo en pantalla) y tanto los 5 controladores de
 * aprobar como ValidacionMasivaService::procesarTransicion() (el que
 * realmente ejecuta el botón "Aprobar" de cada pantalla, vía el modal
 * ValidacionCascadaModal) reusaban ese mismo orden para aplicar los cambios,
 * así que la actualización más vieja se aplicaba último y pisaba a las demás.
 */
class AprobarAplicaUltimaActualizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $comite;

    private User $gerente;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->area = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => null]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->area->id]);
        // area_id null: puedeGestionarArea() siempre true, no depende de jerarquía para este test.
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
    }

    /** Crea 3 actualizaciones "validado" sobre $entidad con timestamps crecientes, en un orden de creación deliberadamente distinto al cronológico para no depender del orden de inserción. */
    private function apilarActualizacionesValidado($entidad, string $campo, array $valoresConMinutos): void
    {
        foreach ($valoresConMinutos as [$valor, $minutos]) {
            $actualizacion = $entidad->actualizaciones()->create([
                'user_id' => $this->gerente->id,
                'mensaje' => "Propongo {$campo} = {$valor}",
                'estado_id' => Estado::validado()->id,
                'data' => ['tipo' => 'cambio', 'campos' => [$campo => $valor]],
            ]);
            $actualizacion->created_at = now()->addMinutes($minutos);
            $actualizacion->save();
        }
    }

    #[Test]
    public function control_aprobado_deja_el_valor_de_la_actualizacion_mas_reciente(): void
    {
        $control = Control::factory()->create([
            'estado_id' => Estado::validado()->id,
            'mitigacion_default' => 9,
            'user_id' => $this->gerente->id,
            'area_id' => $this->area->id,
        ]);

        // Orden de creación deliberadamente no cronológico: la del medio (8) se crea
        // primero, para no depender de que el id ascendente coincida con created_at.
        $this->apilarActualizacionesValidado($control, 'mitigacion_default', [
            [8, 1],   // dfgh: 9 -> 8, la más vieja
            [10, 2],  // sdfg: 8 -> 10
            [3, 3],   // 23452345: 10 -> 3, la más reciente
        ]);

        $this->actingAs($this->comite)
            ->post(route('auditoria.controles.aprobar', $control))
            ->assertRedirect();

        $this->assertEquals(3, $control->fresh()->mitigacion_default);
        $this->assertEquals('aprobado', $control->fresh()->estado->nombre);
    }

    /**
     * Reproduce el caso real reportado: `created_at` es un timestamp de precisión
     * de 1 segundo, así que dos actualizaciones creadas seguidas (gerente valida y
     * aprueba rápido) empatan. Ordenar solo por created_at no desempata de forma
     * confiable — tiene que ganar la de id más alto (la insertada después).
     */
    #[Test]
    public function control_aprobado_deja_el_valor_de_la_actualizacion_mas_reciente_aunque_el_created_at_empate(): void
    {
        $control = Control::factory()->create([
            'estado_id' => Estado::validado()->id,
            'mitigacion_default' => 10,
            'user_id' => $this->gerente->id,
            'area_id' => $this->area->id,
        ]);

        $mismoInstante = now();
        foreach ([3, 6] as $valor) {
            $actualizacion = $control->actualizaciones()->create([
                'user_id' => $this->gerente->id,
                'mensaje' => "Propongo mitigacion_default = {$valor}",
                'estado_id' => Estado::validado()->id,
                'data' => ['tipo' => 'cambio', 'campos' => ['mitigacion_default' => $valor]],
            ]);
            $actualizacion->created_at = $mismoInstante;
            $actualizacion->save();
        }

        $this->actingAs($this->comite)
            ->post(route('auditoria.controles.aprobar', $control))
            ->assertRedirect();

        // 6 es la última insertada (id más alto): tiene que ganar por sobre 3,
        // aunque ambas compartan el mismo created_at.
        $this->assertEquals(6, $control->fresh()->mitigacion_default);
    }

    /**
     * El botón "Aprobar" de la pantalla de Control no pega directo a la ruta del
     * controlador: dispara el evento 'abrir-validacion-cascada' que abre
     * ValidacionCascadaModal, y confirmar() ejecuta ValidacionMasivaService::ejecutar()
     * -> procesarTransicion(). Ese es el camino real que hay que cubrir, no sólo
     * ControlController::aprobar() (que ya no lo usa ningún botón de la UI).
     */
    #[Test]
    public function control_aprobado_desde_el_modal_de_cascada_deja_el_valor_de_la_actualizacion_mas_reciente(): void
    {
        $control = Control::factory()->create([
            'estado_id' => Estado::validado()->id,
            'mitigacion_default' => 9,
            'user_id' => $this->gerente->id,
            'area_id' => $this->area->id,
        ]);

        $this->apilarActualizacionesValidado($control, 'mitigacion_default', [
            [8, 1],
            [10, 2],
            [3, 3],
        ]);

        Livewire::actingAs($this->comite)
            ->test(ValidacionCascadaModal::class)
            ->call('abrir', 'control', $control->id, 'aprobar')
            ->call('confirmar')
            ->assertRedirect();

        $this->assertEquals(3, $control->fresh()->mitigacion_default);
        $this->assertEquals('aprobado', $control->fresh()->estado->nombre);
    }

    #[Test]
    public function objetivo_aprobado_deja_el_nombre_de_la_actualizacion_mas_reciente(): void
    {
        $objetivo = Objetivo::create([
            'nombre' => 'Nombre original',
            'estado_id' => Estado::validado()->id,
            'user_id' => $this->gerente->id,
            'area_id' => $this->area->id,
        ]);

        $this->apilarActualizacionesValidado($objetivo, 'nombre', [
            ['Nombre viejo', 1],
            ['Nombre intermedio', 2],
            ['Nombre final', 3],
        ]);

        $this->actingAs($this->comite)
            ->post(route('auditoria.objetivos.aprobar', $objetivo))
            ->assertRedirect();

        $this->assertEquals('Nombre final', $objetivo->fresh()->nombre);
    }

    #[Test]
    public function plan_de_accion_aprobado_deja_el_nombre_de_la_actualizacion_mas_reciente(): void
    {
        $plan = PlanAccion::factory()->create([
            'estado_id' => Estado::validado()->id,
            'user_id' => $this->gerente->id,
            'area_id' => $this->area->id,
        ]);

        $this->apilarActualizacionesValidado($plan, 'nombre', [
            ['Nombre viejo', 1],
            ['Nombre intermedio', 2],
            ['Nombre final', 3],
        ]);

        $this->actingAs($this->comite)
            ->post(route('auditoria.planes.aprobar', $plan))
            ->assertRedirect();

        $this->assertEquals('Nombre final', $plan->fresh()->nombre);
    }

    #[Test]
    public function tarea_aprobada_deja_el_nombre_de_la_actualizacion_mas_reciente(): void
    {
        $tarea = Tarea::factory()->create([
            'estado_id' => Estado::validado()->id,
            'user_id' => $this->gerente->id,
            'area_id' => $this->area->id,
        ]);

        $this->apilarActualizacionesValidado($tarea, 'nombre', [
            ['Nombre viejo', 1],
            ['Nombre intermedio', 2],
            ['Nombre final', 3],
        ]);

        $this->actingAs($this->comite)
            ->post(route('auditoria.tareas.aprobar', $tarea))
            ->assertRedirect();

        $this->assertEquals('Nombre final', $tarea->fresh()->nombre);
    }

    #[Test]
    public function riesgo_aprobado_deja_el_nombre_de_la_actualizacion_mas_reciente(): void
    {
        $riesgo = Riesgo::factory()->validado()->create(['user_id' => $this->gerente->id]);

        // motivosBloqueoAprobacion() exige un objetivo aprobado asociado.
        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo de soporte',
            'estado_id' => Estado::aprobado()->id,
            'user_id' => $this->gerente->id,
            'area_id' => $this->area->id,
        ]);
        $riesgo->objetivos()->attach($objetivo->id);

        $this->apilarActualizacionesValidado($riesgo, 'nombre', [
            ['Nombre viejo', 1],
            ['Nombre intermedio', 2],
            ['Nombre final', 3],
        ]);

        $this->actingAs($this->comite)
            ->post(route('auditoria.riesgos.aprobar', $riesgo))
            ->assertRedirect();

        $this->assertEquals('Nombre final', $riesgo->fresh()->nombre);
    }
}
