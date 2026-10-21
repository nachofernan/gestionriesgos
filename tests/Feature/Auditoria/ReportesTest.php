<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Exports\Auditoria\ReporteExport;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Naturaleza;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use App\Services\Auditoria\Reportes\FiltrosReporte;
use App\Services\Auditoria\Reportes\ReporteEvolucion;
use App\Services\Auditoria\Reportes\ReportePlanes;
use App\Services\Auditoria\Reportes\ReportePorGerencia;
use App\Services\Auditoria\Reportes\ReportePorTipo;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reportes del panel (D-033): los conteos salen de los accessors del modelo
 * (criticidad inherente/residual, vencimiento del plan) y respetan el sesgo
 * gerencial del panel.
 */
class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerAdmin;

    private Area $gerProd;

    private User $comite;

    private User $canela; // gerente de gerAdmin

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $raiz = Area::create(['nombre' => 'Comité de Riesgo', 'area_padre_id' => null, 'tipo' => TipoArea::Gerencia]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $raiz->id, 'tipo' => TipoArea::Gerencia]);
        $this->gerProd = Area::create(['nombre' => 'Ger. Prod', 'area_padre_id' => $raiz->id, 'tipo' => TipoArea::Gerencia]);

        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
        $this->canela = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerAdmin->id]);
    }

    private function riesgo(Area $area, int $impacto = 5, int $probabilidad = 5, array $extra = []): Riesgo
    {
        return Riesgo::factory()->aprobado()->create([
            'area_id' => $area->id,
            'impacto' => $impacto,
            'probabilidad' => $probabilidad,
            'user_id' => $this->canela->id,
        ] + $extra);
    }

    private function planCon(Riesgo $riesgo, ?string $fechaTarea, int $avance): PlanAccion
    {
        $plan = PlanAccion::factory()->create(['estado_id' => Estado::aprobado()->id, 'area_id' => $riesgo->area_id]);
        $tarea = Tarea::factory()->create([
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $riesgo->area_id,
            'fecha' => $fechaTarea,
            'porcentaje_avance' => $avance,
        ]);
        $plan->tareas()->attach($tarea);
        $riesgo->planesAccion()->attach($plan, ['mitigacion' => 0]);

        return $plan;
    }

    private function cambioDeAvance(Tarea $tarea, string $creadoEn, array $atributos): void
    {
        $tarea->actualizaciones()
            ->create(['user_id' => $this->canela->id, 'mensaje' => 'avance'] + $atributos)
            ->forceFill(['created_at' => $creadoEn])
            ->save();
    }

    private function filaDe(array $filas, string $nombre): array
    {
        return collect($filas)->firstWhere('nombre', $nombre);
    }

    #[Test]
    public function el_reporte_por_gerencia_agrupa_las_subareas_en_su_gerencia(): void
    {
        $sub = Area::create(['nombre' => 'Contaduría', 'area_padre_id' => $this->gerAdmin->id]);
        $this->riesgo($this->gerAdmin);
        $this->riesgo($sub);
        $this->riesgo($this->gerProd);

        $datos = (new ReportePorGerencia($this->comite, new FiltrosReporte))->datos();

        $this->assertSame(2, $this->filaDe($datos['filas'], 'Ger. Admin')['total']);
        $this->assertSame(1, $this->filaDe($datos['filas'], 'Ger. Prod')['total']);
        $this->assertSame(3, $datos['totales']['total']);
    }

    #[Test]
    public function el_reporte_por_gerencia_respeta_la_cascada_del_gerente(): void
    {
        $this->riesgo($this->gerAdmin);
        $this->riesgo($this->gerProd);

        $datos = (new ReportePorGerencia($this->canela, new FiltrosReporte))->datos();

        $this->assertSame(['Ger. Admin'], collect($datos['filas'])->pluck('nombre')->all());
        $this->assertSame(1, $datos['totales']['total']);
    }

    #[Test]
    public function el_reporte_por_gerencia_no_cuenta_borradores(): void
    {
        $this->riesgo($this->gerAdmin);
        Riesgo::factory()->borrador()->create(['area_id' => $this->gerAdmin->id]);

        $datos = (new ReportePorGerencia($this->comite, new FiltrosReporte(soloAprobados: false)))->datos();

        $this->assertSame(1, $datos['totales']['total']);
    }

    #[Test]
    public function el_reporte_por_gerencia_baja_la_criticidad_residual_solo_con_controles_aprobados(): void
    {
        $mitigado = $this->riesgo($this->gerAdmin, 7, 7); // 14 = crítico
        $mitigado->controles()->attach(
            Control::factory()->create(['estado_id' => Estado::aprobado()->id]), ['mitigacion' => 5]
        ); // residual 9 = bajo

        $enBorrador = $this->riesgo($this->gerAdmin, 7, 7);
        $enBorrador->controles()->attach(
            Control::factory()->create(['estado_id' => Estado::borrador()->id]), ['mitigacion' => 5]
        ); // no mitiga: sigue crítico

        $fila = (new ReportePorGerencia($this->comite, new FiltrosReporte))->datos()['totales'];

        $this->assertSame(['critico' => 2, 'moderado' => 0, 'bajo' => 0], $fila['inherente']);
        $this->assertSame(['critico' => 1, 'moderado' => 0, 'bajo' => 1], $fila['residual']);
    }

    #[Test]
    public function el_reporte_por_gerencia_distingue_sin_plan_vigente_y_vencido(): void
    {
        $this->planCon($this->riesgo($this->gerAdmin), today()->subDays(5)->toDateString(), 50);
        $this->planCon($this->riesgo($this->gerAdmin), today()->addDays(5)->toDateString(), 50);
        $this->riesgo($this->gerAdmin);

        $fila = (new ReportePorGerencia($this->comite, new FiltrosReporte))->datos()['totales'];

        $this->assertSame(['sin_plan' => 1, 'vigente' => 1, 'vencido' => 1], $fila['plan']);
    }

    #[Test]
    public function el_reporte_por_gerencia_cuenta_un_riesgo_en_cada_una_de_sus_naturalezas(): void
    {
        $a = Naturaleza::create(['nombre' => 'Operativo']);
        $b = Naturaleza::create(['nombre' => 'Legal']);
        $riesgo = $this->riesgo($this->gerAdmin);
        $riesgo->naturalezas()->sync([$a->id, $b->id]);

        $fila = (new ReportePorGerencia($this->comite, new FiltrosReporte))->datos()['totales'];

        $this->assertSame(1, $fila['total']);
        $this->assertSame(['Operativo' => 1, 'Legal' => 1], $fila['naturalezas']);
    }

    #[Test]
    public function el_reporte_por_tipo_abre_cada_tipo_por_gerencia(): void
    {
        $fraude = TipoRiesgo::factory()->create(['nombre' => 'Fraude']);
        $operativo = TipoRiesgo::factory()->create(['nombre' => 'Operativo']);
        $this->riesgo($this->gerAdmin, extra: ['tipo_riesgo_id' => $fraude->id]);
        $this->riesgo($this->gerProd, extra: ['tipo_riesgo_id' => $fraude->id]);
        $this->riesgo($this->gerProd, extra: ['tipo_riesgo_id' => $operativo->id]);

        $datos = (new ReportePorTipo($this->comite, new FiltrosReporte))->datos();

        $fila = $this->filaDe($datos['filas'], 'Fraude');
        $this->assertSame(2, $fila['total']);
        $this->assertEquals(['Ger. Admin' => 1, 'Ger. Prod' => 1], $fila['gerencias']);
        $this->assertSame(['Ger. Admin', 'Ger. Prod'], $datos['gerencias']);
    }

    #[Test]
    public function la_evolucion_cuenta_el_movimiento_de_riesgos_del_periodo(): void
    {
        $desde = Carbon::parse('2026-06-01');
        $hasta = Carbon::parse('2026-06-30');

        $this->riesgo($this->gerAdmin, extra: ['aprobado_en' => '2026-05-15 10:00']); // previo
        $this->riesgo($this->gerAdmin, extra: ['aprobado_en' => null]);               // carga inicial
        $this->riesgo($this->gerProd, extra: ['aprobado_en' => '2026-06-10 10:00']);  // en el período
        $this->riesgo($this->gerProd, extra: ['aprobado_en' => '2026-07-05 10:00']);  // posterior
        Riesgo::factory()->create([
            'area_id' => $this->gerProd->id,
            'estado_id' => Estado::borrado()->id,
            'rechazado_en' => '2026-06-20 10:00',
        ]);

        $datos = (new ReporteEvolucion($this->comite, new FiltrosReporte(desde: $desde, hasta: $hasta)))->datos();

        $this->assertSame(['inicio' => 2, 'aprobados' => 1, 'rechazados' => 1, 'final' => 3], $datos['resumen']);
        $this->assertSame(1, $datos['sinFecha']);
        $this->assertSame(['inicio' => 0, 'aprobados' => 1, 'rechazados' => 1, 'final' => 1],
            collect($this->filaDe($datos['porGerencia'], 'Ger. Prod'))->except('nombre')->all());
    }

    #[Test]
    public function la_evolucion_reconstruye_el_avance_de_los_planes_desde_el_historial(): void
    {
        $plan = PlanAccion::factory()->create(['estado_id' => Estado::aprobado()->id, 'area_id' => $this->gerAdmin->id]);
        $t1 = Tarea::factory()->create(['estado_id' => Estado::aprobado()->id, 'area_id' => $this->gerAdmin->id, 'porcentaje_avance' => 80]);
        $t2 = Tarea::factory()->create(['estado_id' => Estado::aprobado()->id, 'area_id' => $this->gerAdmin->id, 'porcentaje_avance' => 100]);
        $plan->tareas()->attach([$t1->id, $t2->id]);

        // Edición directa en el período: 20 → 80.
        $this->cambioDeAvance($t1, '2026-06-10 10:00', [
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'edicion', 'diff' => ['campos' => ['porcentaje_avance' => ['antes' => 20, 'despues' => 80]]]],
        ]);
        // Propuesta aprobada en el período: 50 → 100.
        $this->cambioDeAvance($t2, '2026-06-01 09:00', [
            'estado_id' => Estado::aprobado()->id,
            'aprobado_en' => '2026-06-15 10:00',
            'data' => ['tipo' => 'cambio', 'activated_by' => 'x', 'campos' => ['porcentaje_avance' => 100],
                'diff' => ['campos' => ['porcentaje_avance' => ['antes' => 50, 'despues' => 100]]]],
        ]);
        // Propuesta rechazada: no cuenta.
        $this->cambioDeAvance($t1, '2026-06-20 10:00', [
            'estado_id' => Estado::borrado()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['porcentaje_avance' => 90],
                'diff' => ['campos' => ['porcentaje_avance' => ['antes' => 80, 'despues' => 90]]]],
        ]);

        $filtros = new FiltrosReporte(desde: Carbon::parse('2026-06-01'), hasta: Carbon::parse('2026-06-30'));
        $fila = collect((new ReporteEvolucion($this->comite, $filtros))->datos()['planes'])->firstWhere('id', $plan->id);

        $this->assertSame(35, $fila['avance_inicio']); // (20 + 50) / 2
        $this->assertSame(90, $fila['avance_final']);  // (80 + 100) / 2
        $this->assertSame(55, $fila['variacion']);
        $this->assertSame(1, $fila['tareas_completadas']);
    }

    #[Test]
    public function el_reporte_de_planes_cuenta_vencidos_y_sin_avance_por_gerencia(): void
    {
        $vencido = $this->planCon($this->riesgo($this->gerAdmin), today()->subDays(3)->toDateString(), 40);
        $this->planCon($this->riesgo($this->gerAdmin), today()->addDays(3)->toDateString(), 0);
        $this->planCon($this->riesgo($this->gerProd), today()->addDays(3)->toDateString(), 10);

        $datos = (new ReportePlanes($this->canela, new FiltrosReporte))->datos();

        $this->assertSame(['Ger. Admin'], collect($datos['gerencias'])->pluck('nombre')->all());
        $admin = $datos['gerencias'][0];
        $this->assertSame(2, $admin['planes']);
        $this->assertSame(1, $admin['planes_vencidos']);
        $this->assertSame(1, $admin['planes_sin_avance']);
        $this->assertSame(1, $admin['tareas_vencidas']);
        $this->assertTrue(collect($datos['planes'])->firstWhere('id', $vencido->id)['vencido']);
    }

    #[Test]
    public function la_descarga_en_excel_requiere_estar_logueado(): void
    {
        $this->get(route('auditoria.panel.reporte', 'gerencia'))->assertRedirect(route('login'));
    }

    #[Test]
    public function la_descarga_en_excel_trae_solo_la_cascada_del_gerente(): void
    {
        Excel::fake();
        $this->riesgo($this->gerAdmin);
        $this->riesgo($this->gerProd);

        $this->actingAs($this->canela)
            ->get(route('auditoria.panel.reporte', ['reporte' => 'gerencia', 'soloAprobados' => 1]))
            ->assertOk();

        Excel::assertDownloaded('reporte-gerencia-'.now()->format('Ymd').'.xlsx', function (ReporteExport $export) {
            $filas = $export->sheets()[0]->array();

            // Dos filas de encabezado, una gerencia y el total.
            return count($filas) === 6 && $filas[2][0] === 'Ger. Admin' && $filas[3] === array_replace($filas[3], [0 => 'Total', 1 => 1]);
        });
    }

    #[Test]
    public function cada_reporte_se_descarga_en_excel_sin_errores(): void
    {
        $this->planCon($this->riesgo($this->gerAdmin, extra: ['aprobado_en' => now()]), today()->addDay()->toDateString(), 30);

        foreach (['gerencia', 'tipo', 'evolucion', 'planes'] as $reporte) {
            $this->actingAs($this->comite)
                ->get(route('auditoria.panel.reporte', $reporte))
                ->assertOk()
                ->assertDownload('reporte-'.$reporte.'-'.now()->format('Ymd').'.xlsx');
        }
    }

    #[Test]
    public function un_reporte_inexistente_da_404(): void
    {
        $this->actingAs($this->comite)->get('/auditoria/panel/reporte/cualquiera')->assertNotFound();
    }
}
