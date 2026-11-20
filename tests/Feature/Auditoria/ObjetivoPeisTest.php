<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Riesgo\Show\FichaRiesgo;
use App\Livewire\Auditoria\Riesgo\Show\GestionObjetivos;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * D-054: un objetivo PEIS sólo se asocia a riesgos cuyo tipo lo admite (hoy,
 * Corrupción). Cuenta como cualquier objetivo para validar el riesgo, y un riesgo
 * con PEIS no puede pasar a un tipo que no los admite.
 */
class ObjetivoPeisTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private TipoRiesgo $corrupcion;

    private TipoRiesgo $financiero;

    private Objetivo $peis;

    private Objetivo $comun;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Fortalecimiento Institucional', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->gerente()->create(['area_id' => $this->gerencia->id]);

        $this->corrupcion = TipoRiesgo::factory()->create(['nombre' => 'Corrupción', 'restringe_respuesta' => true, 'admite_peis' => true]);
        $this->financiero = TipoRiesgo::factory()->create(['nombre' => 'Financiero', 'admite_peis' => false]);

        $this->peis = $this->objetivo('OE 01: Recertificar el SGAC', esPeis: true);
        $this->comun = $this->objetivo('Objetivo común');
    }

    private function objetivo(string $nombre, bool $esPeis = false): Objetivo
    {
        return Objetivo::create([
            'nombre' => $nombre,
            'es_peis' => $esPeis,
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerencia->id,
            'user_id' => $this->gerente->id,
        ]);
    }

    /** Una fila de $seleccionados con todas las claves que pinta la vista. */
    private function filaSeleccionada(Objetivo $objetivo): array
    {
        return [
            'id' => $objetivo->id, 'nombre' => $objetivo->nombre, 'descripcion' => null,
            'estado' => 'aprobado', 'estado_color' => 'green', 'area' => null, 'fecha_objetivo' => null,
            'estrategico' => false, 'es_peis' => $objetivo->es_peis, 'puede_ver' => true,
            'url' => route('auditoria.objetivos.show', $objetivo->id),
        ];
    }

    private function riesgo(TipoRiesgo $tipo, string $estado = 'borrador'): Riesgo
    {
        return Riesgo::factory()->create([
            'area_id' => $this->gerencia->id,
            'tipo_riesgo_id' => $tipo->id,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
        ]);
    }

    #[Test]
    public function un_riesgo_de_corrupcion_asocia_un_objetivo_peis(): void
    {
        $riesgo = $this->riesgo($this->corrupcion);

        $componente = Livewire::actingAs($this->gerente)
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('abrirModal');
        $this->assertContains($this->peis->id, $componente->viewData('resultados')->pluck('id')->all());

        $componente->call('agregar', $this->peis->id)
            ->call('guardar')
            ->assertSet('error', '');

        $this->assertTrue($riesgo->objetivos()->whereKey($this->peis->id)->exists());
    }

    #[Test]
    public function un_riesgo_de_corrupcion_validado_propone_un_objetivo_peis(): void
    {
        $riesgo = $this->riesgo($this->corrupcion, 'validado');
        $riesgo->objetivos()->attach($this->comun->id);

        Livewire::actingAs($this->gerente)
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $this->peis->id)
            ->call('guardar')
            ->assertSet('error', '');

        $this->assertTrue($riesgo->actualizaciones()->get()->contains(
            fn ($a) => collect($a->data['diff']['relaciones']['objetivos']['agrega'] ?? [])->contains('id', $this->peis->id)
        ));
    }

    #[Test]
    public function un_riesgo_que_no_es_de_corrupcion_no_puede_asociar_un_peis(): void
    {
        $riesgo = $this->riesgo($this->financiero);

        $componente = Livewire::actingAs($this->gerente)
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('abrirModal');
        $resultados = $componente->viewData('resultados')->pluck('id')->all();
        $this->assertNotContains($this->peis->id, $resultados);
        $this->assertContains($this->comun->id, $resultados);

        $componente->call('agregar', $this->peis->id)
            ->assertSet('error', Riesgo::MENSAJE_PEIS_NO_ADMITIDO);

        // $seleccionados es público: armarlo a mano tampoco pasa por guardar().
        $componente->set('seleccionados', [$this->filaSeleccionada($this->peis)])
            ->call('guardar')
            ->assertSet('error', Riesgo::MENSAJE_PEIS_NO_ADMITIDO);

        $this->assertFalse($riesgo->objetivos()->exists());
    }

    #[Test]
    public function un_peis_aprobado_alcanza_para_validar_un_riesgo_de_corrupcion(): void
    {
        $riesgo = $this->riesgo($this->corrupcion);
        $riesgo->objetivos()->attach($this->peis->id);

        $motivos = implode(' ', $riesgo->motivosBloqueoValidacion());

        $this->assertStringNotContainsString('objetivo', $motivos);
    }

    #[Test]
    public function un_riesgo_con_peis_no_cambia_a_un_tipo_que_no_los_admite(): void
    {
        $riesgo = $this->riesgo($this->corrupcion, 'validado');
        $riesgo->objetivos()->attach($this->peis->id);

        Livewire::actingAs($this->gerente)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->set('mensaje', 'Lo paso a financiero')
            ->set('form.tipo_riesgo_id', $this->financiero->id)
            ->set('form.respuesta', 'mitigar')
            ->call('guardar')
            ->assertHasErrors('form.tipo_riesgo_id');

        $this->assertSame($this->corrupcion->id, $riesgo->fresh()->tipo_riesgo_id);
    }

    #[Test]
    public function un_peis_rechazado_no_bloquea_el_cambio_de_tipo(): void
    {
        $riesgo = $this->riesgo($this->corrupcion);
        $rechazado = $this->objetivo('PEIS rechazado', esPeis: true);
        $rechazado->update(['estado_id' => Estado::borrado()->id]);
        $riesgo->objetivos()->attach($rechazado->id);

        $this->assertFalse($riesgo->tienePeis());
    }

    #[Test]
    public function un_gerente_de_otra_gerencia_no_asocia_un_peis(): void
    {
        $riesgo = $this->riesgo($this->corrupcion, 'validado');
        $riesgo->objetivos()->attach($this->comun->id);
        $otraGerencia = Area::create(['nombre' => 'Otra gerencia', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->gerente()->create(['area_id' => $otraGerencia->id]);

        Livewire::actingAs($ajeno)
            ->test(GestionObjetivos::class, ['riesgo' => $riesgo])
            ->set('seleccionados', [$this->filaSeleccionada($this->peis)])
            ->call('guardar')
            ->assertForbidden();

        $this->assertFalse($riesgo->objetivos()->whereKey($this->peis->id)->exists());
    }
}
