<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Trazabilidad estructurada de quién valida/aprueba/rechaza: antes de este
 * cambio no había columnas para esto (sólo un nombre suelto en
 * data['activated_by'], y ni siquiera eso en marcarRechazada()). Cubre tanto
 * el ciclo de vida propio del Riesgo (RiesgoController::validar/aprobar/
 * rechazar) como el de una Actualizacion individual (Actualizacion::
 * marcarValidada/marcarAprobada/marcarRechazada), compartido por
 * Control/Objetivo/PlanAccion/Tarea.
 */
class TrazabilidadValidacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    #[Test]
    public function validar_un_riesgo_deja_quien_y_cuando_en_el_propio_riesgo(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'estado_id' => Estado::validado()->id]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'user_id' => $gerente->id,
            'respuesta' => RespuestaRiesgo::Aceptar,
        ]);
        $riesgo->objetivos()->attach($objetivo);

        $this->actingAs($gerente)->post(route('auditoria.riesgos.validar', $riesgo));

        $riesgo->refresh();
        $this->assertEquals($gerente->id, $riesgo->validado_por_id);
        $this->assertNotNull($riesgo->validado_en);
    }

    #[Test]
    public function aprobar_un_riesgo_deja_quien_y_cuando_en_el_propio_riesgo(): void
    {
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
        $objetivo = Objetivo::create(['nombre' => 'Objetivo', 'estado_id' => Estado::aprobado()->id]);
        $riesgo = Riesgo::factory()->validado()->create([
            'respuesta' => RespuestaRiesgo::Aceptar,
        ]);
        $riesgo->objetivos()->attach($objetivo);

        $this->actingAs($comite)->post(route('auditoria.riesgos.aprobar', $riesgo));

        $riesgo->refresh();
        $this->assertEquals($comite->id, $riesgo->aprobado_por_id);
        $this->assertNotNull($riesgo->aprobado_en);
    }

    #[Test]
    public function rechazar_un_riesgo_deja_quien_y_cuando_en_el_propio_riesgo(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create(['user_id' => $gerente->id]);

        $this->actingAs($gerente)->post(route('auditoria.riesgos.rechazar', $riesgo));

        $riesgo->refresh();
        $this->assertEquals($gerente->id, $riesgo->rechazado_por_id);
        $this->assertNotNull($riesgo->rechazado_en);
    }

    #[Test]
    public function marcar_validada_una_actualizacion_deja_quien_y_cuando(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create();
        $actualizacion = $riesgo->actualizaciones()->create([
            'user_id' => User::factory()->create()->id,
            'mensaje' => 'Propongo un cambio',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['nombre' => 'Nuevo nombre']],
        ]);

        $actualizacion->marcarValidada($gerente);

        $actualizacion->refresh();
        $this->assertEquals($gerente->id, $actualizacion->validado_por_id);
        $this->assertNotNull($actualizacion->validado_en);
    }

    #[Test]
    public function marcar_aprobada_una_actualizacion_deja_quien_y_cuando(): void
    {
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
        $riesgo = Riesgo::factory()->validado()->create();
        $actualizacion = $riesgo->actualizaciones()->create([
            'user_id' => User::factory()->create()->id,
            'mensaje' => 'Propongo un cambio',
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['nombre' => 'Nuevo nombre']],
        ]);

        $actualizacion->marcarAprobada($comite);

        $actualizacion->refresh();
        $this->assertEquals($comite->id, $actualizacion->aprobado_por_id);
        $this->assertNotNull($actualizacion->aprobado_en);
    }

    #[Test]
    public function marcar_rechazada_una_actualizacion_deja_quien_y_cuando(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => null]);
        $riesgo = Riesgo::factory()->borrador()->create();
        $actualizacion = $riesgo->actualizaciones()->create([
            'user_id' => User::factory()->create()->id,
            'mensaje' => 'Propongo un cambio',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['nombre' => 'Nuevo nombre']],
        ]);

        $actualizacion->marcarRechazada($gerente);

        $actualizacion->refresh();
        $this->assertEquals($gerente->id, $actualizacion->rechazado_por_id);
        $this->assertNotNull($actualizacion->rechazado_en);
        $this->assertEquals('borrado', $actualizacion->estado->nombre);
    }
}
