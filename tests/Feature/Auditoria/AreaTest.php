<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Resolución de gerencia sobre el árbol de áreas: Area::esGerencia() y
 * Area::gerencia(), que reemplazan la vieja inferencia por profundidad
 * ("hijo directo de la raíz") por la marca explícita `tipo` = gerencia.
 */
class AreaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function es_gerencia_es_true_solo_para_areas_marcadas(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia', 'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]);
        $sector = Area::create(['nombre' => 'Sector', 'area_padre_id' => $gerencia->id]);

        $this->assertTrue($gerencia->esGerencia());
        $this->assertFalse($sector->esGerencia());
    }

    #[Test]
    public function gerencia_de_un_area_marcada_es_ella_misma(): void
    {
        $gerencia = Area::create(['nombre' => 'Gerencia', 'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]);

        $this->assertEquals($gerencia->id, $gerencia->gerencia()->id);
    }

    #[Test]
    public function gerencia_sube_varios_niveles_hasta_la_primera_marcada(): void
    {
        $comite = Area::create(['nombre' => 'Comité', 'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]);
        $gerencia = Area::create(['nombre' => 'Gerencia', 'area_padre_id' => $comite->id, 'tipo' => TipoArea::Gerencia]);
        $coord = Area::create(['nombre' => 'Coordinación', 'area_padre_id' => $gerencia->id]);
        $sector = Area::create(['nombre' => 'Sector', 'area_padre_id' => $coord->id]);

        // Devuelve la gerencia intermedia, no el comité raíz.
        $this->assertEquals($gerencia->id, $sector->gerencia()->id);
    }

    #[Test]
    public function gerencia_del_comite_raiz_marcado_es_si_mismo(): void
    {
        $comite = Area::create(['nombre' => 'Comité', 'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]);

        $this->assertEquals($comite->id, $comite->gerencia()->id);
    }

    #[Test]
    public function gerencia_devuelve_null_si_ningun_ancestro_esta_marcado(): void
    {
        $raiz = Area::create(['nombre' => 'Raíz', 'area_padre_id' => null]);
        $sector = Area::create(['nombre' => 'Sector', 'area_padre_id' => $raiz->id]);

        $this->assertNull($sector->gerencia());
        $this->assertNull($raiz->gerencia());
    }
}
