<?php

namespace Tests\Feature\Auditoria;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Tarea;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre PlanAccion::getVencimientoAttribute() / getEstaVencidoAttribute(): el
 * vencimiento del plan es la fecha más próxima entre sus tareas todavía
 * pendientes (avance < 100), no la más lejana entre todas.
 */
class VencimientoPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function tarea(array $attrs = []): Tarea
    {
        return Tarea::factory()->create(array_merge([
            'estado_id' => Estado::aprobado()->id,
            'porcentaje_avance' => 20,
        ], $attrs));
    }

    /** @test */
    public function un_plan_esta_vencido_si_alguna_tarea_pendiente_paso_su_fecha(): void
    {
        $plan = PlanAccion::factory()->create();
        $vencida = $this->tarea(['fecha' => today()->subDays(5)]);
        $futura = $this->tarea(['fecha' => today()->addDays(30)]);
        $plan->tareas()->attach([$vencida->id, $futura->id]);

        $plan->refresh()->load('tareas.estado');

        $this->assertTrue($plan->esta_vencido);
        $this->assertTrue($plan->vencimiento->isSameDay($vencida->fecha));
    }

    /** @test */
    public function el_vencimiento_del_plan_es_la_fecha_pendiente_mas_proxima_no_la_mas_lejana(): void
    {
        $plan = PlanAccion::factory()->create();
        $cercana = $this->tarea(['fecha' => today()->addDays(5)]);
        $lejana = $this->tarea(['fecha' => today()->addDays(60)]);
        $plan->tareas()->attach([$cercana->id, $lejana->id]);

        $plan->refresh()->load('tareas.estado');

        $this->assertFalse($plan->esta_vencido);
        $this->assertTrue($plan->vencimiento->isSameDay($cercana->fecha));
    }

    /** @test */
    public function una_tarea_al_100_con_fecha_pasada_no_vence_el_plan(): void
    {
        $plan = PlanAccion::factory()->create();
        $terminada = $this->tarea(['fecha' => today()->subDays(10), 'porcentaje_avance' => 100]);
        $plan->tareas()->attach($terminada->id);

        $plan->refresh()->load('tareas.estado');

        $this->assertFalse($plan->esta_vencido);
        $this->assertNull($plan->vencimiento);
    }

    /** @test */
    public function una_tarea_borrada_no_cuenta_para_el_vencimiento_del_plan(): void
    {
        $plan = PlanAccion::factory()->create();
        $borrada = $this->tarea(['fecha' => today()->subDays(5), 'estado_id' => Estado::where('nombre', 'borrado')->value('id')]);
        $plan->tareas()->attach($borrada->id);

        $plan->refresh()->load('tareas.estado');

        $this->assertFalse($plan->esta_vencido);
        $this->assertNull($plan->vencimiento);
    }

    /** @test */
    public function un_plan_sin_tareas_con_fecha_no_esta_vencido(): void
    {
        $plan = PlanAccion::factory()->create();
        $plan->tareas()->attach($this->tarea(['fecha' => null])->id);

        $plan->refresh()->load('tareas.estado');

        $this->assertFalse($plan->esta_vencido);
        $this->assertNull($plan->vencimiento);
    }
}
