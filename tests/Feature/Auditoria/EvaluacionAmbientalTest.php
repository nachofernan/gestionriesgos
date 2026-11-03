<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\Condicion;
use App\Enums\Auditoria\Responsabilidad;
use App\Enums\Auditoria\Temporalidad;
use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Riesgo\Show\FichaRiesgo;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Dimension;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Sede;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use App\Services\Auditoria\EvaluacionRiesgo;
use Database\Seeders\DimensionSeeder;
use Database\Seeders\EstadoRiesgoSeeder;
use Database\Seeders\SedeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Método de evaluación ambiental (D-043): probabilidad = control + frecuencia,
 * impacto = medio + naturaleza + magnitud + extra por materialidades (tope 10),
 * caracterización obligatoria, y un riesgo no cruza de método al cambiar de tipo.
 */
class EvaluacionAmbientalTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private TipoRiesgo $ambiental;

    private TipoRiesgo $clasico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([EstadoRiesgoSeeder::class, SedeSeeder::class, DimensionSeeder::class]);
        $this->gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->ambiental = TipoRiesgo::factory()->ambiental()->create(['nombre' => 'Ambiental']);
        $this->clasico = TipoRiesgo::factory()->create(['nombre' => 'Operacional']);
    }

    private static function evaluacion(array $respuestas = []): array
    {
        return ['metodo' => 'ambiental', 'respuestas' => $respuestas + [
            'control' => 3, 'frecuencia' => 5, 'medio' => 2, 'naturaleza' => 3, 'magnitud' => 2,
            'materialidad_impacto' => [], 'materialidad_financiera' => [],
        ]];
    }

    private function datosAlta(array $overrides = []): array
    {
        return array_replace_recursive([
            'sedes' => Sede::pluck('id')->all(),
            'dimensiones' => [Dimension::value('id')],
            'nombre' => 'Derrame de aceite',
            'tipo_riesgo_id' => $this->ambiental->id,
            'respuesta' => 'mitigar',
            'area_id' => $this->gerencia->id,
            'temporalidad' => 'actual',
            'condicion' => 'contingencia',
            'responsabilidad' => 'directa',
            'ambiental' => [
                'control' => 3, 'frecuencia' => 5, 'medio' => 2, 'naturaleza' => 3, 'magnitud' => 2,
                'materialidad_impacto' => [1, 4], 'materialidad_financiera' => [6],
            ],
        ], $overrides);
    }

    private function riesgoAmbiental(string $estado = 'borrador'): Riesgo
    {
        return Riesgo::factory()->{$estado}()->create([
            'area_id' => $this->gerencia->id,
            'tipo_riesgo_id' => $this->ambiental->id,
            'probabilidad' => 8,
            'impacto' => 7,
            'evaluacion' => self::evaluacion(),
        ]);
    }

    // -------------------------------------------------------
    // Cálculo
    // -------------------------------------------------------

    public static function marcadas(): array
    {
        return [
            'ninguna' => [0, 0, 0],
            'una' => [1, 0, 1],
            'diez' => [6, 4, 1],
            'once' => [10, 1, 2],
            'las veinte' => [10, 10, 2],
        ];
    }

    #[Test]
    #[DataProvider('marcadas')]
    public function el_extra_por_materialidades_va_de_0_a_2(int $deImpacto, int $financieras, int $extra): void
    {
        $respuestas = [
            'materialidad_impacto' => $deImpacto ? range(1, $deImpacto) : [],
            'materialidad_financiera' => $financieras ? range(1, $financieras) : [],
        ];

        $this->assertSame($extra, EvaluacionRiesgo::extraMaterialidad($respuestas));
        // medio 2 + naturaleza 3 + magnitud 2 = 7, más el extra.
        $this->assertSame(7 + $extra, EvaluacionRiesgo::calcular(self::evaluacion($respuestas))['impacto']);
    }

    #[Test]
    public function el_impacto_ambiental_suma_el_extra_y_no_pasa_de_10(): void
    {
        $maximo = self::evaluacion([
            'medio' => 3, 'naturaleza' => 3, 'magnitud' => 4,
            'materialidad_impacto' => range(1, 10), 'materialidad_financiera' => range(1, 10),
        ]);
        $this->assertSame(10, EvaluacionRiesgo::calcular($maximo)['impacto']);

        // 3 + 3 + 3 + 1 = 10 justo; con +2 sería 11 y queda en 10.
        $this->assertSame(10, EvaluacionRiesgo::calcular(self::evaluacion(['medio' => 3, 'naturaleza' => 3, 'magnitud' => 3, 'materialidad_impacto' => [1]]))['impacto']);
        $this->assertSame(10, EvaluacionRiesgo::calcular(self::evaluacion(['medio' => 3, 'naturaleza' => 3, 'magnitud' => 3, 'materialidad_impacto' => range(1, 10), 'materialidad_financiera' => [1]]))['impacto']);

        $minimo = self::evaluacion(['medio' => 1, 'naturaleza' => 1, 'magnitud' => 1]);
        $this->assertSame(3, EvaluacionRiesgo::calcular($minimo)['impacto']);
    }

    #[Test]
    public function la_probabilidad_ambiental_es_control_mas_frecuencia(): void
    {
        $this->assertSame(2, EvaluacionRiesgo::calcular(self::evaluacion(['control' => 1, 'frecuencia' => 1]))['probabilidad']);
        $this->assertSame(8, EvaluacionRiesgo::calcular(self::evaluacion(['control' => 3, 'frecuencia' => 5]))['probabilidad']);
        $this->assertSame(10, EvaluacionRiesgo::calcular(self::evaluacion(['control' => 5, 'frecuencia' => 5]))['probabilidad']);
    }

    // -------------------------------------------------------
    // Alta
    // -------------------------------------------------------

    #[Test]
    public function el_alta_ambiental_calcula_con_la_matriz_y_guarda_la_caracterizacion(): void
    {
        $this->actingAs($this->gerente)->post(route('auditoria.riesgos.store'), $this->datosAlta())
            ->assertSessionHasNoErrors();

        $riesgo = Riesgo::sole();
        $this->assertSame(8, $riesgo->probabilidad);            // 3 + 5
        $this->assertSame(8, $riesgo->impacto);                 // 2 + 3 + 2 + 1 (3 marcadas)
        $this->assertSame(Temporalidad::Actual, $riesgo->temporalidad);
        $this->assertSame(Condicion::Contingencia, $riesgo->condicion);
        $this->assertSame(Responsabilidad::Directa, $riesgo->responsabilidad);
        $this->assertSame('ambiental', $riesgo->evaluacion['metodo']);
        $this->assertSame([1, 4], $riesgo->evaluacion['respuestas']['materialidad_impacto']);
    }

    #[Test]
    public function un_riesgo_ambiental_exige_la_caracterizacion(): void
    {
        $datos = $this->datosAlta();
        unset($datos['temporalidad'], $datos['condicion'], $datos['responsabilidad']);

        $this->actingAs($this->gerente)->post(route('auditoria.riesgos.store'), $datos)
            ->assertSessionHasErrors(['temporalidad', 'condicion', 'responsabilidad']);
        $this->assertDatabaseCount('riesgos', 0);
    }

    #[Test]
    public function el_alta_ambiental_rechaza_valores_que_la_matriz_no_tiene(): void
    {
        $this->actingAs($this->gerente)->post(route('auditoria.riesgos.store'), $this->datosAlta([
            'ambiental' => ['control' => 2, 'magnitud' => 5, 'materialidad_impacto' => [11]],
        ]))->assertSessionHasErrors(['ambiental.control', 'ambiental.magnitud', 'ambiental.materialidad_impacto.0']);

        $this->assertDatabaseCount('riesgos', 0);
    }

    #[Test]
    public function el_alta_ambiental_no_acepta_el_cuestionario_clasico(): void
    {
        $datos = $this->datosAlta([
            'probabilidad_respuestas' => [1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2],
            'impacto_respuestas' => [1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2],
        ]);
        unset($datos['ambiental']);

        $this->actingAs($this->gerente)->post(route('auditoria.riesgos.store'), $datos)
            ->assertSessionHasErrors('ambiental');
        $this->assertDatabaseCount('riesgos', 0);
    }

    #[Test]
    public function el_alta_clasica_no_guarda_caracterizacion(): void
    {
        $datos = $this->datosAlta([
            'tipo_riesgo_id' => $this->clasico->id,
            'probabilidad_respuestas' => [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2],
            'impacto_respuestas' => [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1],
        ]);

        $this->actingAs($this->gerente)->post(route('auditoria.riesgos.store'), $datos)
            ->assertSessionHasNoErrors();

        $riesgo = Riesgo::sole();
        $this->assertSame('clasica', $riesgo->evaluacion['metodo']);
        $this->assertSame(6, $riesgo->probabilidad);
        $this->assertNull($riesgo->temporalidad);
        $this->assertNull($riesgo->condicion);
        $this->assertNull($riesgo->responsabilidad);
    }

    #[Test]
    public function un_gerente_de_otra_gerencia_no_puede_dar_de_alta_un_riesgo_ambiental_ajeno(): void
    {
        $ajena = Area::create(['nombre' => 'Gerencia B', 'tipo' => TipoArea::Gerencia]);
        $gerenteAjeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $ajena->id]);

        $this->actingAs($gerenteAjeno)->post(route('auditoria.riesgos.store'), $this->datosAlta())
            ->assertForbidden();
        $this->assertDatabaseCount('riesgos', 0);
    }

    // -------------------------------------------------------
    // Recálculo
    // -------------------------------------------------------

    #[Test]
    public function el_recalculo_de_un_riesgo_ambiental_usa_la_matriz(): void
    {
        $riesgo = $this->riesgoAmbiental();

        $this->actingAs($this->gerente)->get(route('auditoria.riesgos.recalcular', $riesgo))
            ->assertOk()
            ->assertSee('Naturaleza del elemento contaminante')
            ->assertViewHas('respuestas', self::evaluacion()['respuestas']);

        $this->actingAs($this->gerente)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'ambiental' => ['control' => 5, 'frecuencia' => 5, 'medio' => 3, 'naturaleza' => 3, 'magnitud' => 4, 'materialidad_impacto' => [2]],
        ])->assertSessionHasNoErrors();

        $riesgo->refresh();
        $this->assertSame(10, $riesgo->probabilidad);
        $this->assertSame(10, $riesgo->impacto);   // 3 + 3 + 4 + 1 = 11 → tope 10
        $this->assertSame([2], $riesgo->evaluacion['respuestas']['materialidad_impacto']);
    }

    #[Test]
    public function un_riesgo_ambiental_sin_evaluacion_ambiental_recalcula_desde_cero(): void
    {
        // Cargado antes de D-043: tiene números del método clásico.
        $riesgo = Riesgo::factory()->borrador()->create([
            'area_id' => $this->gerencia->id, 'tipo_riesgo_id' => $this->ambiental->id, 'probabilidad' => 7, 'impacto' => 5,
            'evaluacion' => ['metodo' => 'clasica', 'respuestas' => ['probabilidad' => [1 => 2], 'impacto' => [1 => 1]]],
        ]);

        $this->actingAs($this->gerente)->get(route('auditoria.riesgos.recalcular', $riesgo))
            ->assertOk()
            ->assertViewHas('respuestas', []);
    }

    #[Test]
    public function un_gerente_de_otra_gerencia_no_puede_recalcular_un_riesgo_ambiental(): void
    {
        $riesgo = $this->riesgoAmbiental();
        $ajena = Area::create(['nombre' => 'Gerencia B', 'tipo' => TipoArea::Gerencia]);
        $gerenteAjeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $ajena->id]);

        $this->actingAs($gerenteAjeno)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'ambiental' => ['control' => 5, 'frecuencia' => 5, 'medio' => 3, 'naturaleza' => 3, 'magnitud' => 4],
        ])->assertForbidden();

        $this->assertSame(8, $riesgo->fresh()->probabilidad);
    }

    // -------------------------------------------------------
    // Cruce de tipo
    // -------------------------------------------------------

    private function datosEdicion(Riesgo $riesgo, int $tipoId): array
    {
        return [
            'sedes' => Sede::pluck('id')->all(),
            'dimensiones' => [Dimension::value('id')],
            'nombre' => $riesgo->nombre,
            'area_id' => $riesgo->area_id,
            'tipo_riesgo_id' => $tipoId,
            'respuesta' => 'mitigar',
        ];
    }

    #[Test]
    public function no_se_puede_pasar_un_riesgo_clasico_a_ambiental_desde_el_borrador(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id, 'tipo_riesgo_id' => $this->clasico->id]);

        $this->actingAs($this->gerente)->put(route('auditoria.riesgos.update', $riesgo), $this->datosEdicion($riesgo, $this->ambiental->id))
            ->assertSessionHasErrors(['tipo_riesgo_id' => Riesgo::MENSAJE_CRUCE_TIPO]);

        $this->assertSame($this->clasico->id, $riesgo->fresh()->tipo_riesgo_id);
    }

    #[Test]
    public function el_tipo_sigue_cambiando_libre_entre_tipos_clasicos(): void
    {
        $otroClasico = TipoRiesgo::factory()->create(['nombre' => 'Financiero']);
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id, 'tipo_riesgo_id' => $this->clasico->id]);

        $this->actingAs($this->gerente)->put(route('auditoria.riesgos.update', $riesgo), $this->datosEdicion($riesgo, $otroClasico->id))
            ->assertSessionHasNoErrors();

        $this->assertSame($otroClasico->id, $riesgo->fresh()->tipo_riesgo_id);
    }

    #[Test]
    public function no_se_puede_sacar_un_riesgo_de_ambiental_desde_la_ficha(): void
    {
        $riesgo = $this->riesgoAmbiental('validado');

        Livewire::actingAs($this->gerente)
            ->test(FichaRiesgo::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->set('mensaje', 'Lo paso a operacional')
            ->set('form.tipo_riesgo_id', $this->clasico->id)
            ->call('guardar')
            ->assertHasErrors(['form.tipo_riesgo_id' => 'in']);

        $this->assertSame($this->ambiental->id, $riesgo->fresh()->tipo_riesgo_id);
        $this->assertCount(0, $riesgo->actualizaciones()->get());
    }

    #[Test]
    public function no_se_puede_proponer_cruzar_a_ambiental_desde_actualizaciones(): void
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->gerencia->id, 'tipo_riesgo_id' => $this->clasico->id]);

        Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Es ambiental')
            ->set('cambios.tipo_riesgo_id', $this->ambiental->id)
            ->call('guardar')
            ->assertHasErrors(['cambios.tipo_riesgo_id' => 'in']);

        $this->assertSame($this->clasico->id, $riesgo->fresh()->tipo_riesgo_id);
    }
}
