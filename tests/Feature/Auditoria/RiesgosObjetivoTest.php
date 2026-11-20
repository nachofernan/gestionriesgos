<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Objetivo\Show\RiesgosObjetivo;
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
 * D-055: los riesgos de un objetivo también se asocian desde el objetivo. Cada
 * alta o baja es un cambio del riesgo: pide `proponer` sobre el riesgo, en
 * borrador se asocia directo y si no queda una propuesta en su historial. Un
 * riesgo no se queda sin objetivos y un PEIS sólo va en riesgos de Corrupción.
 */
class RiesgosObjetivoTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private Objetivo $objetivo;

    private TipoRiesgo $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->gerente()->create(['area_id' => $this->gerencia->id]);
        $this->tipo = TipoRiesgo::factory()->create(['admite_peis' => false]);
        $this->objetivo = $this->objetivo('Continuidad operativa');
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

    private function riesgo(string $estado = 'borrador', ?Area $area = null, ?TipoRiesgo $tipo = null): Riesgo
    {
        return Riesgo::factory()->create([
            'area_id' => ($area ?? $this->gerencia)->id,
            'tipo_riesgo_id' => ($tipo ?? $this->tipo)->id,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
        ]);
    }

    private function componente(?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->gerente)->test(RiesgosObjetivo::class, ['objetivo' => $this->objetivo]);
    }

    #[Test]
    public function un_objetivo_agrega_un_riesgo_en_borrador(): void
    {
        $riesgo = $this->riesgo();

        $componente = $this->componente()->call('abrirModal');
        $this->assertContains($riesgo->id, $componente->viewData('resultados')->pluck('id')->all());

        $componente->call('agregar', $riesgo->id)->assertSet('error', '');

        $this->assertTrue($riesgo->objetivos()->whereKey($this->objetivo->id)->exists());
        $this->assertTrue($riesgo->actualizaciones()->where('mensaje', 'Objetivos asociados')->exists());
    }

    #[Test]
    public function un_objetivo_propone_agregar_un_riesgo_validado(): void
    {
        $empleado = User::factory()->create(['area_id' => $this->gerencia->id]);
        $riesgo = $this->riesgo('validado');
        $riesgo->objetivos()->attach($this->objetivo('Otro objetivo')->id);

        $this->componente($empleado)->call('agregar', $riesgo->id)->assertSet('error', '');

        // Queda como propuesta en el historial del riesgo, no se asocia todavía.
        $this->assertFalse($riesgo->objetivos()->whereKey($this->objetivo->id)->exists());
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('objetivos')->first();
        $this->assertNotNull($propuesta);
        $this->assertSame($this->objetivo->id, $propuesta->data['diff']['relaciones']['objetivos']['agrega'][0]['id']);

        // Y ya no se puede volver a proponer mientras esté pendiente.
        $this->componente($empleado)->call('agregar', $riesgo->id)
            ->assertSet('error', "El riesgo «{$riesgo->nombre}» ya tiene una propuesta pendiente con este objetivo.");
        $this->assertSame(1, $riesgo->actualizaciones()->propuestasPendientes('objetivos')->count());
    }

    #[Test]
    public function no_se_asocia_un_riesgo_de_otra_gerencia_desde_el_objetivo(): void
    {
        $otra = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $ajeno = $this->riesgo('aprobado', $otra);

        $componente = $this->componente()->set('filtroArea', null)->call('abrirModal')->set('filtroArea', null);
        $this->assertNotContains($ajeno->id, $componente->viewData('resultados')->pluck('id')->all());

        $this->componente()->call('agregar', $ajeno->id)->assertForbidden();
        $this->assertFalse($ajeno->objetivos()->exists());
    }

    #[Test]
    public function no_se_quita_el_unico_objetivo_de_un_riesgo(): void
    {
        $riesgo = $this->riesgo();
        $riesgo->objetivos()->attach($this->objetivo->id);

        $this->componente()->call('quitar', $riesgo->id)
            ->assertSet('error', "No se puede quitar: es el único objetivo del riesgo «{$riesgo->nombre}», que necesita al menos uno.");
        $this->assertTrue($riesgo->objetivos()->whereKey($this->objetivo->id)->exists());

        // Con otro objetivo vigente, sí.
        $riesgo->objetivos()->attach($this->objetivo('Otro objetivo')->id);
        $this->componente()->call('quitar', $riesgo->id)->assertSet('error', '');
        $this->assertFalse($riesgo->objetivos()->whereKey($this->objetivo->id)->exists());
    }

    #[Test]
    public function un_peis_solo_agrega_riesgos_de_corrupcion(): void
    {
        $this->objetivo = $this->objetivo('OE 01', esPeis: true);
        $corrupcion = TipoRiesgo::factory()->create(['admite_peis' => true]);
        $deCorrupcion = $this->riesgo(tipo: $corrupcion);
        $comun = $this->riesgo();

        $resultados = $this->componente()->call('abrirModal')->viewData('resultados')->pluck('id')->all();
        $this->assertContains($deCorrupcion->id, $resultados);
        $this->assertNotContains($comun->id, $resultados);

        $this->componente()->call('agregar', $comun->id)->assertSet('error', Riesgo::MENSAJE_PEIS_NO_ADMITIDO);
        $this->assertFalse($comun->objetivos()->exists());

        $this->componente()->call('agregar', $deCorrupcion->id)->assertSet('error', '');
        $this->assertTrue($deCorrupcion->objetivos()->whereKey($this->objetivo->id)->exists());
    }
}
