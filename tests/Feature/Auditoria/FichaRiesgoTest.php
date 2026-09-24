<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Riesgo\Show\FichaRiesgo;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ficha de riesgo/show: edición en el bloque de los datos propios del riesgo fuera
 * de borrador. Manda sólo lo que cambió a Actualizacion::registrarCambioCampos(),
 * que decide si se aplica o queda propuesta (con doble validación si el riesgo es
 * compartido). Un campo con una propuesta pendiente no se vuelve a proponer.
 */
class FichaRiesgoTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private User $empleado;

    private Riesgo $riesgo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);

        $this->riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $this->gerencia->id,
            'nombre' => 'Original',
            'descripcion' => 'Descripción original',
            'respuesta' => RespuestaRiesgo::Evitar,
            'impacto' => 5,
            'probabilidad' => 5,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
    }

    private function editar(User $user, array $form, string $mensaje = 'Corrección')
    {
        $componente = Livewire::actingAs($user)
            ->test(FichaRiesgo::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->set('mensaje', $mensaje);
        foreach ($form as $campo => $valor) {
            $componente->set("form.{$campo}", $valor);
        }

        return $componente->call('guardar');
    }

    #[Test]
    public function un_gerente_edita_la_ficha_de_un_riesgo_validado_y_se_aplica_en_el_acto(): void
    {
        $this->editar($this->gerente, ['descripcion' => 'Nueva descripción'])
            ->assertHasNoErrors()
            ->assertDispatched('riesgo-actualizado')
            ->assertSet('editando', false);

        $this->assertEquals('Nueva descripción', $this->riesgo->refresh()->descripcion);
        $act = $this->riesgo->actualizaciones()->sole();
        $this->assertEquals($this->gerente->name, $act->data['activated_by']);
    }

    #[Test]
    public function un_empleado_edita_la_ficha_y_queda_propuesta_solo_con_los_campos_que_cambiaron(): void
    {
        $this->editar($this->empleado, ['descripcion' => 'Nueva descripción', 'impacto' => 8])
            ->assertHasNoErrors();

        $this->riesgo->refresh();
        $this->assertEquals('Descripción original', $this->riesgo->descripcion);
        $this->assertEquals(5, $this->riesgo->impacto);

        $act = $this->riesgo->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertEquals('borrador', $act->estado->nombre);
        $this->assertEqualsCanonicalizing(['descripcion', 'impacto'], array_keys($act->data['campos']));
        $this->assertEquals(['antes' => 5, 'despues' => 8], $act->data['diff']['campos']['impacto']);
    }

    #[Test]
    public function un_campo_con_propuesta_pendiente_no_se_vuelve_a_proponer(): void
    {
        $this->editar($this->empleado, ['descripcion' => 'Primera propuesta']);

        // Sólo ese campo: no hay nada para guardar.
        $this->editar($this->gerente, ['descripcion' => 'Segunda propuesta'])
            ->assertHasErrors('form');
        $this->assertCount(1, $this->riesgo->actualizaciones()->get());

        // Junto con otro campo libre: se registra sólo el libre.
        $this->editar($this->gerente, ['descripcion' => 'Segunda propuesta', 'nombre' => 'Nombre nuevo'])
            ->assertHasNoErrors();

        $this->riesgo->refresh();
        $this->assertEquals('Nombre nuevo', $this->riesgo->nombre);
        $this->assertEquals('Descripción original', $this->riesgo->descripcion);
        $ultima = $this->riesgo->actualizaciones()->latest('id')->first();
        $this->assertEquals(['nombre'], array_keys($ultima->data['campos']));
    }

    #[Test]
    public function en_un_riesgo_compartido_la_ficha_propone_con_el_voto_del_proponente(): void
    {
        $otra = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $this->riesgo->areas()->syncWithoutDetaching([$otra->id]);

        $this->editar($this->gerente, ['nombre' => 'Nombre nuevo'])->assertHasNoErrors();

        $this->assertEquals('Original', $this->riesgo->refresh()->nombre);
        $act = $this->riesgo->actualizaciones()->sole();
        $this->assertEquals('borrador', $act->estado->nombre);
        $this->assertTrue($act->validacionesGerencia()->where('area_id', $this->gerencia->id)->value('aprueba'));
    }

    #[Test]
    public function la_ficha_no_admite_impacto_ni_probabilidad_fuera_de_rango(): void
    {
        $this->editar($this->gerente, ['impacto' => 11, 'probabilidad' => -1])
            ->assertHasErrors(['form.impacto', 'form.probabilidad']);

        $this->assertEquals(5, $this->riesgo->refresh()->impacto);
        $this->assertCount(0, $this->riesgo->actualizaciones()->get());
    }

    #[Test]
    public function la_ficha_devuelve_403_a_un_gerente_de_otra_gerencia(): void
    {
        $ajena = Area::create(['nombre' => 'Gerencia Ajena', 'tipo' => TipoArea::Gerencia]);
        $gerenteAjeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $ajena->id]);

        Livewire::actingAs($gerenteAjeno)
            ->test(FichaRiesgo::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->assertForbidden();

        Livewire::actingAs($gerenteAjeno)
            ->test(FichaRiesgo::class, ['riesgo' => $this->riesgo])
            ->set('mensaje', 'Intento')
            ->set('form', ['nombre' => 'Hackeado'])
            ->call('guardar')
            ->assertForbidden();

        $this->assertEquals('Original', $this->riesgo->refresh()->nombre);
    }
}
