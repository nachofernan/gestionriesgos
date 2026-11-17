<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Riesgo\Show\FichaRiesgo;
use App\Livewire\Auditoria\Riesgo\Show\GestionControles;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\DimensionSeeder;
use Database\Seeders\EstadoRiesgoSeeder;
use Database\Seeders\SedeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $this->seed([EstadoRiesgoSeeder::class, SedeSeeder::class, DimensionSeeder::class]);

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

    private function riesgoAprobado(Area $area): Riesgo
    {
        return Riesgo::factory()->aprobado()->create([
            'area_id' => $area->id,
            'nombre' => 'Original',
            'respuesta' => RespuestaRiesgo::Evitar,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
    }

    private function editarFicha(User $user, Riesgo $riesgo)
    {
        return Livewire::actingAs($user)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->set('mensaje', 'Corrección de nombre')
            ->set('form.nombre', 'Nuevo nombre');
    }

    private function ultimaActualizacion(Riesgo $riesgo): Actualizacion
    {
        return $riesgo->actualizaciones()->reorder()->orderByDesc('id')->firstOrFail();
    }

    #[Test]
    public function con_doble_rol_propone_sobre_lo_aprobado_de_su_area(): void
    {
        $riesgo = $this->riesgoAprobado($this->propia);

        $this->editarFicha($this->dobleRol, $riesgo)
            ->assertSet('eligiendoRol', false)
            ->call('abrirElegirRol')
            ->assertSet('eligiendoRol', true)
            ->call('guardarComo', 'propuesta')
            ->assertHasNoErrors();

        $propuesta = $this->ultimaActualizacion($riesgo);
        $this->assertSame('validado', $propuesta->estado->nombre);
        $this->assertNull($propuesta->comite_numero);
        $this->assertSame('Original', $riesgo->fresh()->nombre);
    }

    #[Test]
    public function con_doble_rol_establece_como_comite_y_queda_con_los_datos_del_comite(): void
    {
        $riesgo = $this->riesgoAprobado($this->propia);

        $this->editarFicha($this->dobleRol, $riesgo)
            ->call('abrirElegirRol')
            ->set('comite', ['numero' => '42', 'fecha' => today()->toDateString()])
            ->call('guardarComo', 'comite')
            ->assertHasNoErrors();

        $cambio = $this->ultimaActualizacion($riesgo);
        $this->assertSame('aprobado', $cambio->estado->nombre);
        $this->assertSame('42', $cambio->comite_numero);
        $this->assertTrue($cambio->esAprobacion());
        $this->assertSame('Nuevo nombre', $riesgo->fresh()->nombre);
    }

    #[Test]
    public function con_doble_rol_una_fecha_de_comite_futura_no_guarda(): void
    {
        $riesgo = $this->riesgoAprobado($this->propia);

        $this->editarFicha($this->dobleRol, $riesgo)
            ->call('abrirElegirRol')
            ->set('comite', ['numero' => null, 'fecha' => today()->addDay()->toDateString()])
            ->call('guardarComo', 'comite')
            ->assertHasErrors('comite.fecha')
            ->assertSet('eligiendoRol', true);

        $this->assertSame('Original', $riesgo->fresh()->nombre);
    }

    #[Test]
    public function con_doble_rol_fuera_de_su_area_no_elige_pero_confirma_el_comite(): void
    {
        $riesgo = $this->riesgoAprobado($this->ajena);

        $componente = $this->editarFicha($this->dobleRol, $riesgo);
        $this->assertFalse($componente->instance()->requiereElegirRol());
        $this->assertTrue($componente->instance()->estableceComoComite());
        $componente->call('pedirGuardar')->assertSet('eligiendoRol', true);
        $this->assertSame('Original', $riesgo->fresh()->nombre);

        $componente->set('comite', ['numero' => '15', 'fecha' => null])
            ->call('guardarComo', 'comite')
            ->assertHasNoErrors();

        $cambio = $this->ultimaActualizacion($riesgo);
        $this->assertSame('aprobado', $cambio->estado->nombre);
        $this->assertSame('15', $cambio->comite_numero);
        $this->assertSame('Nuevo nombre', $riesgo->fresh()->nombre);
    }

    #[Test]
    public function el_comite_confirma_con_numero_y_fecha_al_editar_lo_aprobado(): void
    {
        $comite = User::factory()->comite()->create(['area_id' => $this->propia->area_padre_id]);
        $riesgo = $this->riesgoAprobado($this->propia);

        $componente = $this->editarFicha($comite, $riesgo)
            ->call('pedirGuardar')
            ->assertSet('eligiendoRol', true);
        $this->assertSame('Original', $riesgo->fresh()->nombre);

        $componente->set('comite', ['numero' => '3', 'fecha' => today()->toDateString()])
            ->call('guardarComo', 'comite')
            ->assertHasNoErrors()
            ->assertSet('eligiendoRol', false);

        $cambio = $this->ultimaActualizacion($riesgo);
        $this->assertSame('aprobado', $cambio->estado->nombre);
        $this->assertSame('3', $cambio->comite_numero);
        $this->assertSame(today()->toDateString(), $cambio->comite_fecha->toDateString());
        $this->assertSame('Nuevo nombre', $riesgo->fresh()->nombre);
    }

    #[Test]
    public function el_gerente_guarda_sin_modal(): void
    {
        $gerente = User::factory()->gerente()->create(['area_id' => $this->propia->id]);
        $riesgo = $this->riesgoAprobado($this->propia);

        $this->editarFicha($gerente, $riesgo)
            ->call('pedirGuardar')
            ->assertSet('eligiendoRol', false)
            ->assertHasNoErrors();

        $this->assertSame('validado', $this->ultimaActualizacion($riesgo)->estado->nombre);
    }

    #[Test]
    public function una_nota_del_comite_no_pide_comite(): void
    {
        $comite = User::factory()->comite()->create(['area_id' => $this->propia->area_padre_id]);
        $riesgo = $this->riesgoAprobado($this->propia);

        Livewire::actingAs($comite)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->set('mensaje', 'Nota del comité')
            ->call('pedirGuardar')
            ->assertSet('eligiendoRol', false)
            ->assertHasNoErrors();

        $nota = $this->ultimaActualizacion($riesgo);
        $this->assertSame('Nota del comité', $nota->mensaje);
        $this->assertNull($nota->estado_id);
    }

    #[Test]
    public function el_comite_elimina_con_numero_y_fecha(): void
    {
        $comite = User::factory()->comite()->create(['area_id' => $this->propia->area_padre_id]);
        $riesgo = $this->riesgoAprobado($this->propia);

        $this->actingAs($comite)->post(route('auditoria.riesgos.eliminacion', $riesgo), [
            'observaciones' => 'Ya no aplica.', 'como' => 'comite', 'comite' => ['numero' => '21', 'fecha' => null],
        ]);

        $this->assertSame('borrado', $riesgo->fresh()->estado->nombre);
        $this->assertSame('21', $riesgo->actualizaciones()->where('data->eliminacion', true)->firstOrFail()->comite_numero);
    }

    #[Test]
    public function un_gerente_que_manda_como_comite_sigue_proponiendo(): void
    {
        $gerente = User::factory()->gerente()->create(['area_id' => $this->propia->id]);
        $riesgo = $this->riesgoAprobado($this->propia);

        $this->editarFicha($gerente, $riesgo)
            ->set('comoComite', true)
            ->call('guardarComo', 'comite')
            ->assertHasNoErrors();

        $propuesta = $this->ultimaActualizacion($riesgo);
        $this->assertSame('validado', $propuesta->estado->nombre);
        $this->assertSame('Original', $riesgo->fresh()->nombre);
    }

    #[Test]
    public function con_doble_rol_establece_como_comite_un_cambio_de_controles(): void
    {
        $riesgo = $this->riesgoAprobado($this->propia);
        $control = Control::factory()->create(['area_id' => $this->propia->id, 'estado_id' => Estado::aprobado()->id]);

        Livewire::actingAs($this->dobleRol)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $control->id)
            ->call('abrirElegirRol')
            ->set('comite', ['numero' => '7', 'fecha' => null])
            ->call('guardarComo', 'comite')
            ->assertHasNoErrors();

        $this->assertTrue($riesgo->fresh()->controles->contains($control));
        $cambio = $this->ultimaActualizacion($riesgo);
        $this->assertSame('aprobado', $cambio->estado->nombre);
        $this->assertSame('7', $cambio->comite_numero);
    }

    #[Test]
    public function con_doble_rol_propone_o_establece_la_eliminacion(): void
    {
        $propuesto = $this->riesgoAprobado($this->propia);
        $this->actingAs($this->dobleRol)
            ->post(route('auditoria.riesgos.eliminacion', $propuesto), ['observaciones' => 'Ya no aplica.', 'como' => 'propuesta']);
        $this->assertSame('aprobado', $propuesto->fresh()->estado->nombre);
        $this->assertSame('validado', $this->ultimaActualizacion($propuesto)->estado->nombre);

        $establecido = $this->riesgoAprobado($this->propia);
        $this->actingAs($this->dobleRol)
            ->post(route('auditoria.riesgos.eliminacion', $establecido), [
                'observaciones' => 'Ya no aplica.', 'como' => 'comite', 'comite' => ['numero' => '9', 'fecha' => null],
            ]);
        $this->assertSame('borrado', $establecido->fresh()->estado->nombre);
        $this->assertSame('9', $establecido->actualizaciones()->where('data->eliminacion', true)->firstOrFail()->comite_numero);
    }

    #[Test]
    public function un_gerente_que_manda_eliminar_como_comite_sigue_proponiendo(): void
    {
        $gerente = User::factory()->gerente()->create(['area_id' => $this->propia->id]);
        $riesgo = $this->riesgoAprobado($this->propia);

        $this->actingAs($gerente)
            ->post(route('auditoria.riesgos.eliminacion', $riesgo), ['observaciones' => 'Ya no aplica.', 'como' => 'comite']);

        $this->assertSame('aprobado', $riesgo->fresh()->estado->nombre);
    }
}
