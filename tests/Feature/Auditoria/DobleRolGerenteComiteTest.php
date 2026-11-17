<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un usuario con rol de gerente y de comité (D-052): como gerente valida en su
 * subárbol y como comité aprueba en todas las áreas. Ve la unión de lo que ve cada
 * rol, sin que el rol de comité agrande lo que hace como gerente.
 */
class DobleRolGerenteComiteTest extends TestCase
{
    use RefreshDatabase;

    private Area $propia;

    private Area $ajena;

    private User $dobleRol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $raiz = Area::create(['nombre' => 'Comité de Riesgo', 'tipo' => TipoArea::Gerencia]);
        $this->propia = Area::create(['nombre' => 'Fortalecimiento Institucional', 'area_padre_id' => $raiz->id, 'tipo' => TipoArea::Gerencia]);
        $this->ajena = Area::create(['nombre' => 'Gerencia Legales', 'area_padre_id' => $raiz->id, 'tipo' => TipoArea::Gerencia]);
        $this->dobleRol = User::factory()->gerente()->comite()->create(['area_id' => $this->propia->id]);
    }

    #[Test]
    public function con_doble_rol_ve_lo_publico_y_sus_borradores_pero_no_borradores_ajenos(): void
    {
        $borradorPropio = Riesgo::factory()->borrador()->create(['area_id' => $this->propia->id]);
        $borradorAjeno = Riesgo::factory()->borrador()->create(['area_id' => $this->ajena->id]);
        $validadoAjeno = Riesgo::factory()->validado()->create(['area_id' => $this->ajena->id]);

        $visibles = Riesgo::visiblePara($this->dobleRol)->pluck('id');

        $this->assertTrue($visibles->contains($borradorPropio->id));
        $this->assertTrue($visibles->contains($validadoAjeno->id));
        $this->assertFalse($visibles->contains($borradorAjeno->id));
        $this->assertTrue($this->dobleRol->can('view', $borradorPropio));
        $this->assertFalse($this->dobleRol->can('view', $borradorAjeno));
    }

    #[Test]
    public function con_doble_rol_aprueba_lo_validado_de_otra_gerencia(): void
    {
        $validadoAjeno = Riesgo::factory()->validado()->create(['area_id' => $this->ajena->id]);

        $this->assertTrue($this->dobleRol->can('aprobar', $validadoAjeno));
        $this->assertTrue($this->dobleRol->can('rechazar', $validadoAjeno));
        $this->assertTrue($this->dobleRol->can('update', $validadoAjeno));
    }

    #[Test]
    public function con_doble_rol_no_valida_un_borrador_de_otra_gerencia(): void
    {
        $borradorPropio = Riesgo::factory()->borrador()->create(['area_id' => $this->propia->id]);
        $borradorAjeno = Riesgo::factory()->borrador()->create(['area_id' => $this->ajena->id]);
        $propuestaAjena = $borradorAjeno->actualizaciones()->create([
            'user_id' => $this->dobleRol->id,
            'mensaje' => 'Propuesta',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['nombre' => 'Otro']],
        ]);

        $this->assertTrue($this->dobleRol->can('validar', $borradorPropio));
        $this->assertFalse($this->dobleRol->can('validar', $borradorAjeno));
        $this->assertFalse($this->dobleRol->can('rechazar', $borradorAjeno));
        $this->assertFalse($this->dobleRol->can('validar', $propuestaAjena));
        $this->assertFalse($this->dobleRol->can('create', [Riesgo::class, $this->ajena->id]));
    }

    #[Test]
    public function con_doble_rol_pendientes_muestra_para_validar_y_para_aprobar(): void
    {
        Riesgo::factory()->borrador()->create(['nombre' => 'Borrador propio', 'area_id' => $this->propia->id]);
        Riesgo::factory()->borrador()->create(['nombre' => 'Borrador ajeno', 'area_id' => $this->ajena->id]);
        Riesgo::factory()->validado()->create(['nombre' => 'Validado ajeno', 'area_id' => $this->ajena->id]);

        $this->actingAs($this->dobleRol)->get(route('auditoria.pendientes.index'))
            ->assertOk()
            ->assertViewHas('paraValidar', fn ($p) => $p['riesgo']->pluck('nombre')->all() === ['Borrador propio'])
            ->assertViewHas('paraAprobar', fn ($p) => $p['riesgo']->pluck('nombre')->all() === ['Validado ajeno']);
    }

    #[Test]
    public function con_doble_rol_recalcula_como_gerente_en_su_area_pero_no_en_la_ajena(): void
    {
        $propio = Riesgo::factory()->aprobado()->create(['area_id' => $this->propia->id]);
        $ajeno = Riesgo::factory()->aprobado()->create(['area_id' => $this->ajena->id]);

        $this->assertNull($propio->motivoBloqueoRecalculo($this->dobleRol));
        $this->assertNotNull($ajeno->motivoBloqueoRecalculo($this->dobleRol));
    }

    #[Test]
    public function el_comite_puro_sigue_sin_ver_borradores_ni_recalcular(): void
    {
        $comite = User::factory()->comite()->create(['area_id' => $this->propia->area_padre_id]);
        $borrador = Riesgo::factory()->borrador()->create(['area_id' => $this->propia->id]);

        $this->assertFalse($comite->can('view', $borrador));
        $this->assertFalse(Riesgo::visiblePara($comite)->pluck('id')->contains($borrador->id));
        $this->assertNotNull($borrador->motivoBloqueoRecalculo($comite));
    }
}
