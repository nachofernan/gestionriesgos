<?php

namespace Tests\Feature\Auditoria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use Database\Seeders\EstadoRiesgoSeeder;

/**
 * Cubre la pantalla de Pendientes: qué entidades/Actualizaciones ve cada rol
 * según el área que gestiona, espejando la jerarquía ya usada en
 * ActualizacionPermisoTest (comité raíz, dos gerencias, sub-áreas).
 */
class PendienteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Area $comite;
    private Area $gerAdmin;
    private Area $gerProd;
    private Area $sectA;

    private User $lucia;   // comité
    private User $canela;  // gerente gerAdmin
    private User $grassi;  // gerente gerProd
    private User $nacho;   // empleado sectA

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->comite   = Area::create(['nombre' => 'Comité',     'area_padre_id' => null]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $this->comite->id]);
        $this->gerProd  = Area::create(['nombre' => 'Ger. Prod',  'area_padre_id' => $this->comite->id]);
        $this->sectA    = Area::create(['nombre' => 'Sector A',   'area_padre_id' => $this->gerAdmin->id]);

        $this->lucia  = User::factory()->create(['rol' => 'comite',   'area_id' => $this->comite->id]);
        $this->canela = User::factory()->create(['rol' => 'gerente',  'area_id' => $this->gerAdmin->id]);
        $this->grassi = User::factory()->create(['rol' => 'gerente',  'area_id' => $this->gerProd->id]);
        $this->nacho  = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectA->id]);
    }

    /** @test */
    public function un_empleado_no_puede_ver_la_pantalla_de_pendientes(): void
    {
        $this->actingAs($this->nacho)
            ->get(route('auditoria.pendientes.index'))
            ->assertForbidden();
    }

    /** @test */
    public function el_gerente_ve_en_para_validar_los_riesgos_en_borrador_de_su_propia_gerencia(): void
    {
        $propio = Riesgo::factory()->borrador()->create(['nombre' => 'Riesgo de mi sector', 'area_id' => $this->sectA->id]);
        $ajeno  = Riesgo::factory()->borrador()->create(['nombre' => 'Riesgo de la otra gerencia', 'area_id' => $this->gerProd->id]);

        $respuesta = $this->actingAs($this->canela)->get(route('auditoria.pendientes.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Riesgo de mi sector');
        $respuesta->assertDontSee('Riesgo de la otra gerencia');
    }

    /** @test */
    public function el_gerente_no_ve_riesgos_ya_validados_en_para_validar(): void
    {
        $validado = Riesgo::factory()->validado()->create(['nombre' => 'Riesgo ya validado', 'area_id' => $this->sectA->id]);

        $respuesta = $this->actingAs($this->canela)->get(route('auditoria.pendientes.index'));

        $respuesta->assertDontSee('Riesgo ya validado');
    }

    /** @test */
    public function el_comite_ve_en_para_aprobar_los_riesgos_validados_de_cualquier_gerencia(): void
    {
        $admin = Riesgo::factory()->validado()->create(['nombre' => 'Riesgo validado admin', 'area_id' => $this->sectA->id]);
        $prod  = Riesgo::factory()->validado()->create(['nombre' => 'Riesgo validado prod', 'area_id' => $this->gerProd->id]);

        $respuesta = $this->actingAs($this->lucia)->get(route('auditoria.pendientes.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('Riesgo validado admin');
        $respuesta->assertSee('Riesgo validado prod');
    }

    /** @test */
    public function una_actualizacion_en_borrador_solo_aparece_para_el_gerente_del_area_de_la_entidad(): void
    {
        $objetivoPropio = Objetivo::create([
            'nombre'    => 'Objetivo propio',
            'estado_id' => Estado::aprobado()->id,
            'area_id'   => $this->sectA->id,
            'user_id'   => $this->nacho->id,
        ]);
        $objetivoAjeno = Objetivo::create([
            'nombre'    => 'Objetivo ajeno',
            'estado_id' => Estado::aprobado()->id,
            'area_id'   => $this->gerProd->id,
            'user_id'   => $this->nacho->id,
        ]);

        $objetivoPropio->actualizaciones()->create([
            'user_id'   => $this->nacho->id,
            'mensaje'   => 'Cambio propuesto propio',
            'estado_id' => Estado::borrador()->id,
            'data'      => ['tipo' => 'cambio'],
        ]);
        $objetivoAjeno->actualizaciones()->create([
            'user_id'   => $this->nacho->id,
            'mensaje'   => 'Cambio propuesto ajeno',
            'estado_id' => Estado::borrador()->id,
            'data'      => ['tipo' => 'cambio'],
        ]);

        $respuesta = $this->actingAs($this->canela)->get(route('auditoria.pendientes.index'));

        $respuesta->assertSee('Cambio propuesto propio');
        $respuesta->assertDontSee('Cambio propuesto ajeno');
    }

    /** @test */
    public function no_hay_nada_pendiente_muestra_el_mensaje_vacio(): void
    {
        $respuesta = $this->actingAs($this->canela)->get(route('auditoria.pendientes.index'));

        $respuesta->assertOk();
        $respuesta->assertSee('No tenés nada pendiente por ahora.');
    }
}
