<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Dimension;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Sede;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use App\Services\Auditoria\EvaluacionRiesgo;
use Database\Seeders\DimensionSeeder;
use Database\Seeders\EstadoRiesgoSeeder;
use Database\Seeders\SedeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El riesgo guarda las respuestas vigentes de su wizard (D-042): el alta y el
 * recálculo las dejan en `evaluacion`, fuera de borrador cambian sólo por el
 * ciclo de propuestas aunque el número no se mueva, y los riesgos existentes
 * se completan desde su historial.
 */
class EvaluacionRiesgoTest extends TestCase
{
    use RefreshDatabase;

    private const PROBABILIDAD = [1 => 2, 2 => 1, 3 => 0, 4 => 1, 5 => 2]; // 6

    private const IMPACTO = [1 => 1, 2 => 1, 3 => 0, 4 => 0, 5 => 1]; // 3

    private Area $gerencia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([EstadoRiesgoSeeder::class, SedeSeeder::class, DimensionSeeder::class]);
        $this->gerencia = Area::create(['nombre' => 'Gerencia A', 'tipo' => TipoArea::Gerencia]);
    }

    private function evaluacion(array $probabilidad = self::PROBABILIDAD, array $impacto = self::IMPACTO): array
    {
        return ['metodo' => 'clasica', 'respuestas' => ['probabilidad' => $probabilidad, 'impacto' => $impacto]];
    }

    private function recalcular(User $user, Riesgo $riesgo, array $probabilidad, array $impacto)
    {
        return $this->actingAs($user)->post(route('auditoria.riesgos.recalcular.store', $riesgo), [
            'probabilidad_respuestas' => $probabilidad,
            'impacto_respuestas' => $impacto,
        ]);
    }

    #[Test]
    public function el_alta_guarda_la_evaluacion_y_calcula_con_ella(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);

        $this->actingAs($gerente)->post(route('auditoria.riesgos.store'), [
            'sedes' => Sede::pluck('id')->all(),
            'dimensiones' => [Dimension::value('id')],
            'nombre' => 'Riesgo de prueba',
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
            'probabilidad_respuestas' => self::PROBABILIDAD,
            'impacto_respuestas' => self::IMPACTO,
            'respuesta' => 'mitigar',
            'area_id' => $this->gerencia->id,
        ])->assertSessionHasNoErrors();

        $riesgo = Riesgo::sole();
        $this->assertEquals($this->evaluacion(), $riesgo->evaluacion);
        $this->assertEquals(6, $riesgo->probabilidad);
        $this->assertEquals(3, $riesgo->impacto);
    }

    #[Test]
    public function el_recalculo_en_borrador_reemplaza_la_evaluacion(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'area_id' => $this->gerencia->id, 'probabilidad' => 6, 'impacto' => 3, 'evaluacion' => $this->evaluacion(),
        ]);
        $nuevaProbabilidad = [1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2];

        $this->recalcular($gerente, $riesgo, $nuevaProbabilidad, self::IMPACTO);

        $riesgo->refresh();
        $this->assertEquals(10, $riesgo->probabilidad);
        $this->assertEquals($this->evaluacion($nuevaProbabilidad), $riesgo->evaluacion);
    }

    #[Test]
    public function el_wizard_de_recalculo_arranca_con_las_respuestas_vigentes(): void
    {
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $riesgo = Riesgo::factory()->borrador()->create([
            'area_id' => $this->gerencia->id, 'probabilidad' => 6, 'impacto' => 3, 'evaluacion' => $this->evaluacion(),
        ]);

        $this->actingAs($gerente)->get(route('auditoria.riesgos.recalcular', $riesgo))
            ->assertOk()
            ->assertViewHas('respuestas', $this->evaluacion()['respuestas']);
    }

    #[Test]
    public function un_recalculo_que_solo_cambia_respuestas_queda_como_propuesta(): void
    {
        $empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $this->gerencia->id, 'probabilidad' => 6, 'impacto' => 3, 'evaluacion' => $this->evaluacion(),
        ]);
        // Sube una pregunta y baja otra: la probabilidad sigue en 6.
        $mismoNumero = [1 => 1, 2 => 2, 3 => 0, 4 => 1, 5 => 2];

        $this->recalcular($empleado, $riesgo, $mismoNumero, self::IMPACTO);

        $this->assertEquals($this->evaluacion(), $riesgo->fresh()->evaluacion);
        $propuesta = $riesgo->actualizaciones()->latest('id')->firstOrFail();
        $this->assertEquals('cambio', $propuesta->data['tipo']);
        $this->assertEquals('borrador', $propuesta->estado->nombre);
        $this->assertEquals(['evaluacion'], array_keys($propuesta->data['diff']['campos']));
        $this->assertSame('Respuestas del cuestionario', $propuesta->diffLegible()['campos'][0]['etiqueta']);
        $this->assertSame('Prob. 1·2·0·1·2 · Imp. 1·1·0·0·1', $propuesta->diffLegible()['campos'][0]['despues']);
    }

    #[Test]
    public function la_propuesta_de_recalculo_aplica_la_evaluacion_recien_al_aprobarse(): void
    {
        $empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $this->gerencia->id, 'probabilidad' => 6, 'impacto' => 3, 'evaluacion' => $this->evaluacion(),
        ]);
        $nuevoImpacto = [1 => 2, 2 => 2, 3 => 2, 4 => 0, 5 => 0];

        $this->recalcular($empleado, $riesgo, self::PROBABILIDAD, $nuevoImpacto);
        $this->assertEquals(3, $riesgo->fresh()->impacto);

        // El riesgo está validado: validar la propuesta la aplica en el acto.
        $riesgo->actualizaciones()->latest('id')->firstOrFail()->marcarValidada($gerente);

        $riesgo->refresh();
        $this->assertEquals(6, $riesgo->impacto);
        $this->assertEquals($this->evaluacion(self::PROBABILIDAD, $nuevoImpacto), $riesgo->evaluacion);
    }

    #[Test]
    public function el_backfill_toma_las_respuestas_del_ultimo_recalculo_aplicado(): void
    {
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->gerencia->id, 'probabilidad' => 10, 'impacto' => 3]);
        $usuario = User::factory()->create();
        $registrar = fn (array $data, string $estado) => $riesgo->actualizaciones()->create([
            'user_id' => $usuario->id, 'mensaje' => 'x', 'estado_id' => Estado::where('nombre', $estado)->value('id'), 'data' => $data,
        ]);
        $todoDos = [1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2];

        $registrar(['tipo' => 'creacion', 'respuestas' => ['probabilidad' => self::PROBABILIDAD, 'impacto' => self::IMPACTO]], 'aprobado');
        $registrar(['tipo' => 'cambio', 'activated_by' => 'Gerente', 'respuestas' => ['probabilidad' => $todoDos, 'impacto' => self::IMPACTO]], 'validado');
        // La última es una propuesta pendiente: no cuenta.
        $registrar(['tipo' => 'cambio', 'respuestas' => ['probabilidad' => self::PROBABILIDAD, 'impacto' => $todoDos]], 'borrador');

        $this->assertEquals(
            $this->evaluacion($todoDos, self::IMPACTO),
            EvaluacionRiesgo::desdeHistorial($riesgo->id, 3, 10),
        );
    }

    #[Test]
    public function el_backfill_descarta_respuestas_que_no_explican_el_numero(): void
    {
        // Las respuestas suman 6/3, pero el número quedó en 9/9 (editado a mano con D-015).
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->gerencia->id, 'probabilidad' => 9, 'impacto' => 9]);
        $riesgo->actualizaciones()->create([
            'user_id' => User::factory()->create()->id, 'mensaje' => 'x', 'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'creacion', 'respuestas' => ['probabilidad' => self::PROBABILIDAD, 'impacto' => self::IMPACTO]],
        ]);

        $this->assertNull(EvaluacionRiesgo::desdeHistorial($riesgo->id, 9, 9));
    }
}
