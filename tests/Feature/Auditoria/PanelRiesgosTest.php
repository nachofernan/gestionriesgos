<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\PanelRiesgos;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cubre el núcleo sagrado del Panel de Riesgos: el sesgo gerencial (cada gerente
 * ve sólo su cascada de áreas, el comité ve todo), la regla de estados (nunca
 * borradores, ni siquiera de la propia gerencia) y el toggle "solo aprobados"
 * (encendido = sólo aprobados; apagado = suma validados). También verifica que el
 * riel residual refleje la mitigación de un control aprobado.
 */
class PanelRiesgosTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerAdmin;

    private Area $gerProd;

    private User $canela; // gerente de gerAdmin

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $comite = Area::create(['nombre' => 'Comité', 'area_padre_id' => null]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]);
        $this->gerProd = Area::create(['nombre' => 'Ger. Prod', 'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]);

        $this->canela = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerAdmin->id]);
    }

    private function crearRiesgo(string $estado, Area $area, int $impacto = 5, int $probabilidad = 5): Riesgo
    {
        return Riesgo::factory()->{$estado}()->create([
            'area_id' => $area->id,
            'impacto' => $impacto,
            'probabilidad' => $probabilidad,
            'user_id' => $this->canela->id,
        ]);
    }

    #[Test]
    public function el_gerente_ve_solo_los_riesgos_de_su_cascada_no_los_de_otra_gerencia(): void
    {
        $propio = $this->crearRiesgo('aprobado', $this->gerAdmin);
        $ajeno = $this->crearRiesgo('aprobado', $this->gerProd);

        Livewire::actingAs($this->canela)
            ->test(PanelRiesgos::class)
            ->assertViewHas('total', 1)
            ->assertViewHas('riesgosJs', fn ($js) => collect($js)->pluck('codigo')->contains($propio->codigo)
                && ! collect($js)->pluck('codigo')->contains($ajeno->codigo));
    }

    #[Test]
    public function una_subarea_de_la_propia_gerencia_si_entra_al_panel(): void
    {
        $subArea = Area::create(['nombre' => 'Contaduría', 'area_padre_id' => $this->gerAdmin->id]);
        $propio = $this->crearRiesgo('aprobado', $subArea);

        Livewire::actingAs($this->canela)
            ->test(PanelRiesgos::class)
            ->assertViewHas('total', 1)
            ->assertViewHas('riesgosJs', fn ($js) => collect($js)->pluck('codigo')->contains($propio->codigo));
    }

    #[Test]
    public function el_comite_ve_los_riesgos_de_todas_las_gerencias(): void
    {
        $comiteUser = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
        $this->crearRiesgo('aprobado', $this->gerAdmin);
        $this->crearRiesgo('aprobado', $this->gerProd);

        Livewire::actingAs($comiteUser)
            ->test(PanelRiesgos::class)
            ->assertViewHas('total', 2);
    }

    #[Test]
    public function el_panel_nunca_incluye_borradores_ni_apagando_el_toggle(): void
    {
        $this->crearRiesgo('borrador', $this->gerAdmin);

        Livewire::actingAs($this->canela)
            ->test(PanelRiesgos::class)
            ->set('soloAprobados', false)
            ->assertViewHas('total', 0);
    }

    #[Test]
    public function el_toggle_solo_aprobados_encendido_deja_fuera_los_validados(): void
    {
        $this->crearRiesgo('aprobado', $this->gerAdmin);
        $this->crearRiesgo('validado', $this->gerAdmin);

        $componente = Livewire::actingAs($this->canela)->test(PanelRiesgos::class);

        // Encendido por defecto: sólo el aprobado.
        $componente->assertViewHas('total', 1);

        // Apagado: aprobado + validado.
        $componente->set('soloAprobados', false)->assertViewHas('total', 2);
    }

    #[Test]
    public function el_riel_residual_refleja_la_mitigacion_de_un_control_aprobado(): void
    {
        $riesgo = $this->crearRiesgo('aprobado', $this->gerAdmin, 5, 5); // valor_total = 10
        $control = Control::factory()->create(['estado_id' => Estado::aprobado()->id]);
        $riesgo->controles()->attach($control, ['mitigacion' => 4]); // residual = 6

        Livewire::actingAs($this->canela)
            ->test(PanelRiesgos::class)
            ->assertViewHas('pistaInherente', fn ($p) => $p[10] === 1 && array_sum($p) === 1)
            ->assertViewHas('pistaResidual', fn ($p) => $p[6] === 1 && array_sum($p) === 1);
    }
}
