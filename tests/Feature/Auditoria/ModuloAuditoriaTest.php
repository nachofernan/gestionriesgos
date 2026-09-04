<?php

namespace Tests\Feature\Auditoria;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModuloAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    #[Test]
    public function un_riesgo_se_crea_con_estado_borrador_por_defecto()
    {
        $tipo = TipoRiesgo::factory()->create();

        $riesgo = Riesgo::create([
            'nombre' => 'Riesgo de prueba',
            'tipo_riesgo_id' => $tipo->id,
        ]);

        $this->assertEquals('borrador', $riesgo->estado->nombre);
    }

    #[Test]
    public function un_riesgo_puede_tener_muchos_planes_de_accion()
    {
        $riesgo = Riesgo::factory()->create();
        $planes = PlanAccion::factory()->count(3)->create();

        $riesgo->planesAccion()->attach($planes);

        $this->assertCount(3, $riesgo->planesAccion);
    }

    #[Test]
    public function un_plan_de_accion_puede_tener_muchos_riesgos()
    {
        $plan = PlanAccion::factory()->create();
        $riesgos = Riesgo::factory()->count(3)->create();

        $plan->riesgos()->attach($riesgos);

        $this->assertCount(3, $plan->riesgos);
    }

    #[Test]
    public function un_plan_de_accion_puede_tener_muchas_tareas()
    {
        $plan = PlanAccion::factory()->create();
        $tareas = Tarea::factory()->count(3)->create();

        $plan->tareas()->attach($tareas);

        $this->assertCount(3, $plan->tareas);
    }

    #[Test]
    public function una_tarea_puede_pertenecer_a_muchos_planes()
    {
        $tarea = Tarea::factory()->create();
        $planes = PlanAccion::factory()->count(3)->create();

        $tarea->planesAccion()->attach($planes);

        $this->assertCount(3, $tarea->planesAccion);
    }

    #[Test]
    public function una_tarea_tiene_fecha_y_actualizaciones()
    {
        $user = User::factory()->create();
        $tarea = Tarea::factory()->create(['fecha' => '2024-05-15']);

        $tarea->actualizaciones()->create([
            'user_id' => $user->id,
            'mensaje' => 'Avance 1',
        ]);

        $this->assertEquals('2024-05-15', $tarea->fecha->format('Y-m-d'));
        $this->assertCount(1, $tarea->actualizaciones);
        $this->assertEquals('Avance 1', $tarea->actualizaciones->first()->mensaje);
    }

    #[Test]
    public function riesgo_tiene_codigo_y_mayor_criticidad()
    {
        $riesgo = Riesgo::factory()->create([
            'codigo' => 'R-TEST',
            'impacto' => 8,
            'probabilidad' => 8,
            'mayor_criticidad' => true,
        ]);

        $this->assertEquals('R-TEST', $riesgo->codigo);
        $this->assertTrue($riesgo->mayor_criticidad);
    }

    #[Test]
    public function un_riesgo_puede_tener_muchos_controles_con_mitigacion_pivot()
    {
        $riesgo = Riesgo::factory()->create();
        $control = Control::factory()->create();

        $riesgo->controles()->attach($control->id, ['mitigacion' => 5]);

        $this->assertCount(1, $riesgo->controles);
        $this->assertEquals(5, $riesgo->controles->first()->pivot->mitigacion);
    }

    #[Test]
    public function objetivo_tiene_fecha_nullable()
    {
        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo sin fecha',
        ]);

        $this->assertNull($objetivo->fecha_objetivo);

        $objetivo->update(['fecha_objetivo' => '2024-12-31']);
        $this->assertEquals('2024-12-31', $objetivo->fecha_objetivo->format('Y-m-d'));
    }
}
