<?php

namespace Tests\Feature\Auditoria;

use App\Models\Auditoria\PlanAccion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica que los seeders generen datos coherentes en la relación plan ↔ tarea:
 * un plan no puede estar más avanzado en el ciclo de vida que sus tareas ni al
 * revés (ver PlanAccionSeeder). Sin esta regla aparecían planes aprobados con
 * tareas en borrador.
 */
class CoherenciaSeedersTest extends TestCase
{
    use RefreshDatabase;

    /** Estados de tarea admitidos para cada estado de plan (espejo del seeder). */
    private array $tareasCompatibles = [
        'borrador' => ['borrador', 'validado'],
        'validado' => ['validado', 'aprobado'],
        'aprobado' => ['validado', 'aprobado'],
    ];

    /** @test */
    public function ningun_plan_tiene_tareas_incompatibles_con_su_estado()
    {
        $this->seed(DatabaseSeeder::class);

        $planes = PlanAccion::with(['estado', 'tareas.estado'])->get();

        $this->assertNotEmpty($planes, 'El seeder no generó planes de acción.');

        foreach ($planes as $plan) {
            $permitidos = $this->tareasCompatibles[$plan->estado->nombre];

            foreach ($plan->tareas as $tarea) {
                $this->assertContains(
                    $tarea->estado->nombre,
                    $permitidos,
                    "El plan {$plan->codigo} ({$plan->estado->nombre}) tiene la tarea ".
                    "'{$tarea->nombre}' en estado '{$tarea->estado->nombre}', incompatible."
                );
            }
        }
    }
}
