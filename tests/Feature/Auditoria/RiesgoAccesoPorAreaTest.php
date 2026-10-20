<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Naturaleza;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Sede;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use App\Policies\Auditoria\ActualizacionPolicy;
use App\Policies\Auditoria\RiesgoPolicy;
use Database\Seeders\EstadoRiesgoSeeder;
use Database\Seeders\NaturalezaSeeder;
use Database\Seeders\SedeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El riesgo pertenece sólo a su area_id, como el resto de las entidades (se
 * eliminó la multigerencia, ver DECISIONES). Cubre que el acceso salga de la
 * jerarquía sobre area_id: el creador en una sub-área y su gerente lo gestionan,
 * otra gerencia no, y una propuesta la resuelve un solo gerente, sin votos.
 */
class RiesgoAccesoPorAreaTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private Area $subarea;

    private Area $otraGerencia;

    private User $empleado;

    private User $gerente;

    private User $gerenteAjeno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([EstadoRiesgoSeeder::class, SedeSeeder::class, NaturalezaSeeder::class]);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $this->subarea = Area::create(['nombre' => 'Sistemas', 'area_padre_id' => $this->gerencia->id]);
        $this->otraGerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);

        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->subarea->id]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->gerenteAjeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->otraGerencia->id]);
    }

    private function riesgo(string $estado = 'borrador', ?Area $area = null): Riesgo
    {
        return Riesgo::factory()->{$estado}()->create([
            'area_id' => ($area ?? $this->subarea)->id,
            'user_id' => $this->empleado->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ])->load('estado');
    }

    #[Test]
    public function el_empleado_creador_en_una_subarea_ve_y_edita_su_borrador(): void
    {
        $riesgo = $this->riesgo();
        $policy = new RiesgoPolicy;

        $this->assertTrue($policy->view($this->empleado, $riesgo));
        $this->assertTrue($policy->update($this->empleado, $riesgo));
        $this->assertTrue(Riesgo::visiblePara($this->empleado)->whereKey($riesgo->id)->exists());
        $this->actingAs($this->empleado)->get(route('auditoria.riesgos.show', $riesgo))->assertOk();
    }

    #[Test]
    public function el_gerente_gestiona_y_valida_el_borrador_de_su_subarea(): void
    {
        $riesgo = $this->riesgo();
        $policy = new RiesgoPolicy;

        $this->assertTrue($policy->update($this->gerente, $riesgo));
        $this->assertTrue($policy->validar($this->gerente, $riesgo));
        $this->assertTrue($policy->rechazar($this->gerente, $riesgo));
        $this->assertTrue(Riesgo::visiblePara($this->gerente)->whereKey($riesgo->id)->exists());
    }

    #[Test]
    public function el_gerente_de_otra_gerencia_no_ve_ni_valida_el_borrador(): void
    {
        $riesgo = $this->riesgo();
        $policy = new RiesgoPolicy;

        $this->assertFalse($policy->view($this->gerenteAjeno, $riesgo));
        $this->assertFalse($policy->validar($this->gerenteAjeno, $riesgo));
        $this->assertFalse(Riesgo::visiblePara($this->gerenteAjeno)->whereKey($riesgo->id)->exists());

        $this->actingAs($this->gerenteAjeno)->get(route('auditoria.riesgos.show', $riesgo))->assertForbidden();
        $this->actingAs($this->gerenteAjeno)->put(route('auditoria.riesgos.update', $riesgo), [
            'sedes' => Sede::pluck('id')->all(),
            'naturalezas' => [Naturaleza::value('id')],
            'nombre' => 'Intento ajeno',
            'tipo_riesgo_id' => $riesgo->tipo_riesgo_id,
            'area_id' => $this->otraGerencia->id,
            'respuesta' => 'mitigar',
        ])->assertForbidden();
        $this->actingAs($this->gerenteAjeno)->post(route('auditoria.riesgos.validar', $riesgo))->assertForbidden();
    }

    #[Test]
    public function mover_el_area_de_un_borrador_mueve_el_acceso(): void
    {
        $gerenteMovido = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->otraGerencia->id]);
        $riesgo = $this->riesgo();

        $riesgo->update(['area_id' => $this->otraGerencia->id]);
        $riesgo->refresh()->load('estado');

        $policy = new RiesgoPolicy;
        $this->assertTrue($policy->update($gerenteMovido, $riesgo));
        $this->assertFalse($policy->update($this->gerente, $riesgo));
        $this->assertFalse($policy->update($this->empleado, $riesgo));
    }

    #[Test]
    public function una_propuesta_sobre_un_riesgo_validado_se_aplica_con_la_validacion_de_su_gerente(): void
    {
        $riesgo = $this->riesgo('validado');

        $propuesta = Actualizacion::registrarCambioCampos($riesgo, $this->empleado, 'Cambio de nombre', ['nombre' => 'Nombre propuesto']);
        $this->assertSame('borrador', $propuesta->estado->nombre);
        $this->assertNotSame('Nombre propuesto', $riesgo->fresh()->nombre);

        $policy = new ActualizacionPolicy;
        $this->assertFalse($policy->validar($this->gerenteAjeno, $propuesta));
        $this->assertTrue($policy->validar($this->gerente, $propuesta));

        $propuesta->marcarValidada($this->gerente);

        $this->assertSame('Nombre propuesto', $riesgo->fresh()->nombre);
    }
}
