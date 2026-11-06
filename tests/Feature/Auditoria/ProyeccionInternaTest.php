<?php

namespace Tests\Feature\Auditoria;

use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proyección del borrador interno (D-044): además de lo aprobado, descuentan los
 * controles, planes y tareas en borrador con flag. Un borrador sin flag o un
 * validado no proyectan, y el residual oficial no cambia.
 */
class ProyeccionInternaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function estado(string $nombre): int
    {
        return Estado::where('nombre', $nombre)->value('id');
    }

    /** Riesgo interno de total 16. */
    private function riesgo(): Riesgo
    {
        return Riesgo::factory()->create(['impacto' => 8, 'probabilidad' => 8, 'interno' => true]);
    }

    private function control(string $estado, bool $interno = false): Control
    {
        return Control::factory()->create(['estado_id' => $this->estado($estado), 'interno' => $interno]);
    }

    private function plan(string $estado, bool $interno = false): PlanAccion
    {
        return PlanAccion::factory()->create(['estado_id' => $this->estado($estado), 'interno' => $interno]);
    }

    private function tarea(int $avance, string $estado, bool $interno = false): Tarea
    {
        return Tarea::factory()->create(['porcentaje_avance' => $avance, 'estado_id' => $this->estado($estado), 'interno' => $interno]);
    }

    private function cargado(Riesgo $riesgo): Riesgo
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])->findOrFail($riesgo->id);
    }

    #[Test]
    public function el_residual_proyectado_suma_lo_aprobado_y_lo_interno(): void
    {
        // El ejemplo de la propuesta: 16 → oficial 13, proyectado 6.
        $riesgo = $this->riesgo();
        $riesgo->controles()->attach($this->control('aprobado')->id, ['mitigacion' => 3]);
        $riesgo->controles()->attach($this->control('borrador', interno: true)->id, ['mitigacion' => 4]);
        $riesgo->controles()->attach($this->control('borrador')->id, ['mitigacion' => 2]);

        $plan = $this->plan('borrador', interno: true);
        $plan->tareas()->attach([
            $this->tarea(50, 'borrador', interno: true)->id,
            $this->tarea(100, 'borrador', interno: true)->id,
            $this->tarea(0, 'validado')->id,
        ]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 4]);

        $riesgo = $this->cargado($riesgo);

        $this->assertSame(13, $riesgo->valor_residual);
        $this->assertSame(7, $riesgo->mitigacion_controles_proyectada);
        $this->assertSame(3, $riesgo->mitigacion_planes_proyectada); // 4 × 75%, hacia abajo
        $this->assertSame(6, $riesgo->valor_residual_proyectado);
    }

    #[Test]
    public function un_borrador_sin_flag_o_un_validado_no_proyectan(): void
    {
        $riesgo = $this->riesgo();
        $riesgo->controles()->attach($this->control('borrador')->id, ['mitigacion' => 4]);
        $riesgo->controles()->attach($this->control('validado')->id, ['mitigacion' => 4]);

        $planValidado = $this->plan('validado');
        $planValidado->tareas()->attach($this->tarea(100, 'aprobado')->id);
        $riesgo->planesAccion()->attach($planValidado->id, ['mitigacion' => 4]);

        $planSinFlag = $this->plan('borrador');
        $planSinFlag->tareas()->attach($this->tarea(100, 'borrador', interno: true)->id);
        $riesgo->planesAccion()->attach($planSinFlag->id, ['mitigacion' => 4]);

        $this->assertSame(16, $this->cargado($riesgo)->valor_residual_proyectado);
    }

    #[Test]
    public function un_validado_con_flag_no_proyecta(): void
    {
        // El flag sólo vale en borrador: si quedara puesto en otro estado no cuenta.
        $riesgo = $this->riesgo();
        $riesgo->controles()->attach($this->control('validado', interno: true)->id, ['mitigacion' => 4]);

        $this->assertSame(16, $this->cargado($riesgo)->valor_residual_proyectado);
    }

    #[Test]
    public function una_tarea_sin_flag_o_validada_no_mueve_el_avance_proyectado(): void
    {
        $plan = $this->plan('borrador', interno: true);
        $plan->tareas()->attach([
            $this->tarea(40, 'borrador', interno: true)->id,
            $this->tarea(80, 'borrador', interno: true)->id,
        ]);
        $this->assertSame(60, $plan->fresh()->load('tareas.estado')->avance_proyectado);

        $plan->tareas()->attach([
            $this->tarea(0, 'validado')->id,
            $this->tarea(0, 'borrador')->id,
        ]);
        $plan = $plan->fresh()->load('tareas.estado');

        $this->assertSame(60, $plan->avance_proyectado);
        $this->assertNull($plan->avance);
        $this->assertSame(6, $plan->mitigacionEfectivaProyectada(10));
    }

    #[Test]
    public function la_proyeccion_cuenta_las_tareas_aprobadas_con_las_internas(): void
    {
        $plan = $this->plan('borrador', interno: true);
        $plan->tareas()->attach([
            $this->tarea(100, 'aprobado')->id,
            $this->tarea(50, 'borrador', interno: true)->id,
        ]);

        $this->assertSame(75, $plan->fresh()->load('tareas.estado')->avance_proyectado);
    }

    #[Test]
    public function la_proyeccion_no_cambia_el_residual_oficial(): void
    {
        $riesgo = $this->riesgo();
        $riesgo->controles()->attach($this->control('borrador', interno: true)->id, ['mitigacion' => 5]);
        $plan = $this->plan('borrador', interno: true);
        $plan->tareas()->attach($this->tarea(100, 'borrador', interno: true)->id);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 5]);

        $riesgo = $this->cargado($riesgo);

        $this->assertSame(16, $riesgo->valor_residual);
        $this->assertSame(0, $riesgo->mitigacion_controles);
        $this->assertSame(0, $riesgo->mitigacion_planes);
        $this->assertSame(6, $riesgo->valor_residual_proyectado);
    }

    #[Test]
    public function el_residual_proyectado_nunca_baja_de_cero(): void
    {
        $riesgo = $this->riesgo();
        $riesgo->controles()->attach($this->control('borrador', interno: true)->id, ['mitigacion' => 20]);

        $this->assertSame(0, $this->cargado($riesgo)->valor_residual_proyectado);
    }
}
