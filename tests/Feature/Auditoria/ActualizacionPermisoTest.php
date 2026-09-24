<?php

namespace Tests\Feature\Auditoria;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\User;
use App\Policies\Auditoria\ActualizacionPolicy;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifica que solo el dueño del elemento (y superiores jerárquicos) puede
 * validar, rechazar o proponer actualizaciones.
 *
 * Jerarquía:
 *   comite (raíz)
 *   ├── gerAdmin   ← canela (gerente)
 *   │   ├── sectA  ← nacho  (empleado)
 *   │   └── sectB  ← tito   (empleado, hermano de nacho)
 *   └── gerProd    ← grassi (gerente)
 *       └── sectC  ← nocetti (empleado)
 */
class ActualizacionPermisoTest extends TestCase
{
    use RefreshDatabase;

    private Area $comite;

    private Area $gerAdmin;

    private Area $gerProd;

    private Area $sectA;

    private Area $sectB;

    private Area $sectC;

    private User $lucia;    // comité   – comite

    private User $canela;   // gerente  – gerAdmin

    private User $nacho;    // empleado – sectA

    private User $tito;     // empleado – sectB (hermano de nacho)

    private User $grassi;   // gerente  – gerProd

    private User $nocetti;  // empleado – sectC

    private int $borradorId;

    private int $validadoId;

    private int $aprobadoId;

    // -------------------------------------------------------
    // Setup
    // -------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EstadoRiesgoSeeder::class);

        $this->comite = Area::create(['nombre' => 'Comité',     'area_padre_id' => null]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $this->comite->id]);
        $this->gerProd = Area::create(['nombre' => 'Ger. Prod',  'area_padre_id' => $this->comite->id]);
        $this->sectA = Area::create(['nombre' => 'Sector A',   'area_padre_id' => $this->gerAdmin->id]);
        $this->sectB = Area::create(['nombre' => 'Sector B',   'area_padre_id' => $this->gerAdmin->id]);
        $this->sectC = Area::create(['nombre' => 'Sector C',   'area_padre_id' => $this->gerProd->id]);

        $this->lucia = User::factory()->create(['rol' => 'comite',   'area_id' => $this->comite->id]);
        $this->canela = User::factory()->create(['rol' => 'gerente',  'area_id' => $this->gerAdmin->id]);
        $this->nacho = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectA->id]);
        $this->tito = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectB->id]);
        $this->grassi = User::factory()->create(['rol' => 'gerente',  'area_id' => $this->gerProd->id]);
        $this->nocetti = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectC->id]);

        $this->borradorId = Estado::borrador()->id;
        $this->validadoId = Estado::validado()->id;
        $this->aprobadoId = Estado::aprobado()->id;
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function objetivo(Area $area, ?int $estadoId = null): Objetivo
    {
        return Objetivo::create([
            'nombre' => 'Objetivo test',
            'estado_id' => $estadoId ?? $this->aprobadoId,
            'area_id' => $area->id,
            'user_id' => $this->nacho->id,
        ]);
    }

    private function actualizacion(Objetivo $objetivo, int $estadoId, ?User $autor = null): Actualizacion
    {
        return $objetivo->actualizaciones()->create([
            'user_id' => ($autor ?? $this->nacho)->id,
            'mensaje' => 'Propuesta de cambio',
            'estado_id' => $estadoId,
            'data' => ['tipo' => 'cambio'],
        ]);
    }

    private function policy(): ActualizacionPolicy
    {
        return new ActualizacionPolicy;
    }

    // -------------------------------------------------------
    // Policy: validar
    // -------------------------------------------------------

    #[Test]
    public function gerente_puede_validar_actualizacion_de_su_propia_gerencia(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->assertTrue($this->policy()->validar($this->canela, $actualizacion));
    }

    #[Test]
    public function gerente_puede_validar_actualizacion_de_subarea_de_su_gerencia(): void
    {
        $objetivo = $this->objetivo($this->sectB);
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->assertTrue($this->policy()->validar($this->canela, $actualizacion));
    }

    #[Test]
    public function gerente_no_puede_validar_actualizacion_de_otra_gerencia(): void
    {
        $objetivo = $this->objetivo($this->sectC); // gerProd
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->assertFalse($this->policy()->validar($this->canela, $actualizacion));
    }

    #[Test]
    public function gerente_de_produccion_no_puede_validar_actualizacion_de_administracion(): void
    {
        $objetivo = $this->objetivo($this->sectA); // gerAdmin
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->assertFalse($this->policy()->validar($this->grassi, $actualizacion));
    }

    #[Test]
    public function gerente_no_puede_validar_actualizacion_que_ya_no_esta_en_borrador(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->validadoId);

        $this->assertFalse($this->policy()->validar($this->canela, $actualizacion));
    }

    #[Test]
    public function empleado_no_puede_validar_actualizaciones(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->assertFalse($this->policy()->validar($this->nacho, $actualizacion));
    }

    // -------------------------------------------------------
    // Policy: rechazar
    // -------------------------------------------------------

    #[Test]
    public function gerente_puede_rechazar_actualizacion_de_su_propia_gerencia(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->assertTrue($this->policy()->rechazar($this->canela, $actualizacion));
    }

    #[Test]
    public function gerente_no_puede_rechazar_actualizacion_de_otra_gerencia(): void
    {
        $objetivo = $this->objetivo($this->sectC); // gerProd
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->assertFalse($this->policy()->rechazar($this->canela, $actualizacion));
    }

    #[Test]
    public function gerente_no_puede_rechazar_actualizacion_ya_validada(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->validadoId);

        $this->assertFalse($this->policy()->rechazar($this->canela, $actualizacion));
    }

    #[Test]
    public function comite_puede_rechazar_una_actualizacion_validada_que_espera_su_aprobacion(): void
    {
        $objetivo = $this->objetivo($this->sectC);
        $actualizacion = $this->actualizacion($objetivo, $this->validadoId);

        $this->assertTrue($this->policy()->rechazar($this->lucia, $actualizacion));
    }

    #[Test]
    public function comite_no_puede_rechazar_una_actualizacion_ya_aprobada(): void
    {
        $objetivo = $this->objetivo($this->sectC);
        $actualizacion = $this->actualizacion($objetivo, $this->aprobadoId);

        $this->assertFalse($this->policy()->rechazar($this->lucia, $actualizacion));
    }

    // -------------------------------------------------------
    // Policy: aprobar (comité)
    // -------------------------------------------------------

    #[Test]
    public function comite_puede_aprobar_actualizacion_de_cualquier_gerencia(): void
    {
        $objAdmin = $this->objetivo($this->sectA);
        $actualizAdmin = $this->actualizacion($objAdmin, $this->validadoId);

        $objProd = $this->objetivo($this->sectC);
        $actualizProd = $this->actualizacion($objProd, $this->validadoId);

        $this->assertTrue($this->policy()->aprobar($this->lucia, $actualizAdmin));
        $this->assertTrue($this->policy()->aprobar($this->lucia, $actualizProd));
    }

    #[Test]
    public function comite_no_puede_aprobar_actualizacion_en_borrador(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->assertFalse($this->policy()->aprobar($this->lucia, $actualizacion));
    }

    // -------------------------------------------------------
    // Policy: cancelar
    // -------------------------------------------------------

    #[Test]
    public function autor_puede_cancelar_su_propia_actualizacion_en_borrador(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId, $this->nacho);

        $this->assertTrue($this->policy()->cancelar($this->nacho, $actualizacion));
    }

    #[Test]
    public function otro_usuario_no_puede_cancelar_actualizacion_ajena(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId, $this->nacho);

        $this->assertFalse($this->policy()->cancelar($this->tito, $actualizacion));
    }

    #[Test]
    public function autor_no_puede_cancelar_actualizacion_ya_validada(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->validadoId, $this->nacho);

        $this->assertFalse($this->policy()->cancelar($this->nacho, $actualizacion));
    }

    // -------------------------------------------------------
    // HTTP: POST actualizaciones/{actualizacion}/validar
    // -------------------------------------------------------

    #[Test]
    public function http_validar_retorna_403_para_gerente_de_otra_gerencia(): void
    {
        $objetivo = $this->objetivo($this->sectC); // gerProd
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->actingAs($this->canela) // gerente de gerAdmin
            ->post(route('auditoria.actualizaciones.validar', $actualizacion))
            ->assertForbidden();
    }

    #[Test]
    public function http_validar_redirige_para_gerente_de_su_propia_gerencia(): void
    {
        $objetivo = $this->objetivo($this->sectA); // gerAdmin
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->actingAs($this->canela)
            ->post(route('auditoria.actualizaciones.validar', $actualizacion))
            ->assertRedirect();
    }

    #[Test]
    public function http_validar_retorna_403_para_empleado(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->actingAs($this->nacho)
            ->post(route('auditoria.actualizaciones.validar', $actualizacion))
            ->assertForbidden();
    }

    // -------------------------------------------------------
    // HTTP: POST actualizaciones/{actualizacion}/rechazar
    // -------------------------------------------------------

    #[Test]
    public function http_rechazar_retorna_403_para_gerente_de_otra_gerencia(): void
    {
        $objetivo = $this->objetivo($this->sectC); // gerProd
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->actingAs($this->canela) // gerente de gerAdmin
            ->post(route('auditoria.actualizaciones.rechazar', $actualizacion))
            ->assertForbidden();
    }

    #[Test]
    public function http_rechazar_redirige_para_gerente_de_su_propia_gerencia(): void
    {
        $objetivo = $this->objetivo($this->sectA);
        $actualizacion = $this->actualizacion($objetivo, $this->borradorId);

        $this->actingAs($this->canela)
            ->post(route('auditoria.actualizaciones.rechazar', $actualizacion))
            ->assertRedirect();
    }

    // -------------------------------------------------------
    // HTTP: POST objetivos/{objetivo}/actualizaciones (store)
    // -------------------------------------------------------

    #[Test]
    public function http_store_retorna_403_si_usuario_no_gestiona_el_area_del_objetivo(): void
    {
        $objetivo = $this->objetivo($this->sectC); // gerProd

        $this->actingAs($this->canela) // gerAdmin — no puede gestionar sectC
            ->post(route('auditoria.objetivos.actualizaciones.store', $objetivo), [
                'mensaje' => 'Propongo actualizar este objetivo',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function http_store_redirige_si_usuario_gestiona_el_area_del_objetivo(): void
    {
        $objetivo = $this->objetivo($this->sectA); // gerAdmin

        $this->actingAs($this->canela) // gerAdmin — puede gestionar sectA
            ->post(route('auditoria.objetivos.actualizaciones.store', $objetivo), [
                'mensaje' => 'Propongo actualizar este objetivo',
            ])
            ->assertRedirect();
    }

    #[Test]
    public function http_store_retorna_403_para_empleado_fuera_de_su_area(): void
    {
        $objetivo = $this->objetivo($this->sectB); // sectB, nacho es de sectA

        $this->actingAs($this->nacho)
            ->post(route('auditoria.objetivos.actualizaciones.store', $objetivo), [
                'mensaje' => 'Propongo actualizar este objetivo',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function http_store_redirige_para_empleado_en_su_propia_area(): void
    {
        $objetivo = $this->objetivo($this->sectA); // nacho es de sectA

        $this->actingAs($this->nacho)
            ->post(route('auditoria.objetivos.actualizaciones.store', $objetivo), [
                'mensaje' => 'Propongo actualizar este objetivo',
            ])
            ->assertRedirect();
    }
}
