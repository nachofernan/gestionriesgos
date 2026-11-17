<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Control\Show\FichaControl;
use App\Livewire\Auditoria\Control\Show\ValorControl;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use App\Services\Auditoria\EvaluacionControl;
use App\Services\Auditoria\ValidacionMasivaService;
use Database\Factories\Auditoria\ControlFactory;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El valor de un control sale de su cuestionario (D-051): 7 preguntas que
 * suman de 1 a 10, y si el control es una expresión de deseo mitiga 0. El valor
 * es del control y vale igual en cada riesgo que mitiga. Lo contesta el área por
 * el circuito de siempre (directo en borrador, propuesta fuera de borrador), es
 * obligatorio para validar y aprobar, y los controles de antes conservan su
 * número hasta que alguien lo conteste. Sobre un control aprobado, el auditor
 * lo reevalúa en el acto, con comentario y adjuntos.
 */
class ValorControlTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private Area $otraGerencia;

    private User $gerente;

    private User $empleado;

    private User $comite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->otraGerencia = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->gerente()->create(['area_id' => $this->gerencia->id]);
        $this->empleado = User::factory()->empleado()->create(['area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->comite()->create(['area_id' => null]);
    }

    private function control(string $estado, ?int $puntaje = null, ?int $numeroViejo = null): Control
    {
        $factory = $puntaje === null ? Control::factory() : Control::factory()->valorado($puntaje);

        return $factory->create(array_filter([
            'nombre' => 'Control de stock',
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
            'mitigacion_default' => $numeroViejo,
        ], fn ($v) => $v !== null));
    }

    /** Riesgo aprobado de total 12 con el control asociado (evidencia 100%). */
    private function riesgoCon(Control $control, ?Area $area = null): Riesgo
    {
        $riesgo = Riesgo::factory()->aprobado()->create(['impacto' => 6, 'probabilidad' => 6, 'area_id' => ($area ?? $this->gerencia)->id]);
        $riesgo->controles()->attach($control->id);

        return $riesgo;
    }

    private function residual(Riesgo $riesgo): int
    {
        return Riesgo::with(['controles.estado', 'planesAccion.estado', 'planesAccion.tareas.estado'])
            ->findOrFail($riesgo->id)->valor_residual;
    }

    /** El formulario del componente con todas las respuestas en «sí» y sus detalles: puntaje 10. */
    private function cuestionarioCompleto(array $cambios = []): array
    {
        return array_replace_recursive([
            'respuestas' => ['operativo' => 1, 'documentado' => 1, 'sistema' => 1, 'responsable' => 1, 'frecuencia' => 1, 'evidencia' => 1, 'detecta' => 1],
            'procedimiento' => 'PR-COM-04 Compras',
            'sistemas' => ['SAP'],
            'sistema_otro' => '',
            'frecuencia' => 'mensual',
        ], $cambios);
    }

    private function contestar(User $user, Control $control, array $cuestionario)
    {
        return Livewire::actingAs($user)
            ->test(ValorControl::class, ['control' => $control])
            ->call('abrir')
            ->set('cuestionario', $cuestionario)
            ->call('guardar');
    }

    // -------------------------------------------------------
    // Cálculo
    // -------------------------------------------------------

    #[Test]
    public function el_puntaje_suma_lo_que_vale_cada_respuesta(): void
    {
        $todoSi = ['respuestas' => array_fill_keys(['operativo', 'documentado', 'sistema', 'responsable', 'frecuencia', 'evidencia', 'detecta'], 1)];
        $todoNo = ['respuestas' => array_fill_keys(array_keys($todoSi['respuestas']), 0)];

        $this->assertSame(10, EvaluacionControl::puntaje($todoSi));
        $this->assertSame(1, EvaluacionControl::puntaje($todoNo)); // manual vale 1
        $this->assertSame(0, EvaluacionControl::mitigacion($todoNo));

        foreach (range(2, 10) as $puntaje) {
            $this->assertSame($puntaje, EvaluacionControl::puntaje(ControlFactory::evaluacion($puntaje)), "puntaje {$puntaje}");
        }
    }

    #[Test]
    public function el_numero_del_control_sale_del_cuestionario_y_vale_en_cada_riesgo(): void
    {
        $control = $this->control('aprobado', 7);
        $riesgo = $this->riesgoCon($control);
        $ajeno = $this->riesgoCon($control, $this->otraGerencia);

        $this->assertSame(7, $control->fresh()->mitigacion_default);
        $this->assertSame(5, $this->residual($riesgo));
        $this->assertSame(5, $this->residual($ajeno));
    }

    #[Test]
    public function un_control_expresion_de_deseo_muestra_puntaje_y_mitiga_cero(): void
    {
        $control = Control::factory()->valorado(9, operativo: false)->create([
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
        ]);
        $riesgo = $this->riesgoCon($control);

        $control = $control->fresh();
        $this->assertSame(9, $control->puntaje);
        $this->assertSame(0, $control->mitigacion_default);
        $this->assertSame('deficiente', $control->clasificacion()['etiqueta']);
        $this->assertSame(12, $this->residual($riesgo));
    }

    #[Test]
    public function la_clasificacion_del_control_sigue_sus_umbrales(): void
    {
        $esperado = [1 => 'deficiente', 3 => 'deficiente', 4 => 'moderado', 6 => 'moderado', 7 => 'suficiente', 10 => 'suficiente'];
        foreach ($esperado as $puntaje => $etiqueta) {
            $this->assertSame($etiqueta, EvaluacionControl::clasificacion($puntaje)['etiqueta'], "puntaje {$puntaje}");
        }
        $this->assertSame('rojo', EvaluacionControl::clasificacion(3)['color']);
        $this->assertSame('amarillo', EvaluacionControl::clasificacion(5)['color']);
        $this->assertSame('verde', EvaluacionControl::clasificacion(8)['color']);
        $this->assertSame('deficiente', EvaluacionControl::clasificacion(10, operativo: false)['etiqueta']);

        $this->assertSame('suficiente', $this->control('aprobado', 8)->clasificacion()['etiqueta']);
        // Sin cuestionario se clasifica por el número que traía.
        $this->assertSame('moderado', $this->control('aprobado', numeroViejo: 5)->clasificacion()['etiqueta']);
    }

    #[Test]
    public function un_control_viejo_sin_cuestionario_conserva_su_numero(): void
    {
        $control = $this->control('aprobado', numeroViejo: 5);
        $riesgo = $this->riesgoCon($control);

        $this->assertNull($control->fresh()->evaluacion);
        $this->assertSame(5, $control->fresh()->puntaje);
        $this->assertSame(7, $this->residual($riesgo)); // 12 - 5

        // Otro cambio del control no le toca el número.
        $control->update(['nombre' => 'Control de stock revisado']);
        $this->assertSame(5, $control->fresh()->mitigacion_default);
    }

    // -------------------------------------------------------
    // Cuestionario y circuito
    // -------------------------------------------------------

    #[Test]
    public function el_cuestionario_exige_las_respuestas_y_el_detalle_de_cada_si(): void
    {
        $control = $this->control('borrador');

        $sinRespuesta = $this->cuestionarioCompleto();
        unset($sinRespuesta['respuestas']['detecta']);
        $this->contestar($this->gerente, $control, $sinRespuesta)
            ->assertHasErrors('cuestionario.respuestas.detecta');

        $sinDetalles = ['procedimiento' => '', 'sistemas' => [], 'sistema_otro' => '', 'frecuencia' => ''] + $this->cuestionarioCompleto();
        $this->contestar($this->gerente, $control, $sinDetalles)
            ->assertHasErrors(['cuestionario.procedimiento', 'cuestionario.sistemas', 'cuestionario.frecuencia']);

        $this->contestar($this->gerente, $control, ['sistemas' => ['Un sistema inventado']] + $this->cuestionarioCompleto())
            ->assertHasErrors('cuestionario.sistemas.0');

        $this->assertNull($control->fresh()->evaluacion);

        // «Otro» con texto alcanza sin marcar ninguno de la lista.
        $this->contestar($this->gerente, $control, ['sistemas' => [], 'sistema_otro' => 'Tango'] + $this->cuestionarioCompleto())
            ->assertHasNoErrors();
        $this->assertSame('Tango', $control->fresh()->evaluacion['sistema_otro']);
    }

    #[Test]
    public function en_borrador_el_cuestionario_se_aplica_directo(): void
    {
        $control = $this->control('borrador');

        // Los detalles de un «no» se descartan.
        $this->contestar($this->gerente, $control, $this->cuestionarioCompleto([
            'respuestas' => ['documentado' => 0, 'sistema' => 0],
        ]))->assertHasNoErrors()->assertDispatched('control-actualizado');

        $control = $control->fresh();
        $this->assertSame(6, $control->mitigacion_default); // 1 + 0 + 1 + 1 + 1 + 1 + 1
        $this->assertNull($control->evaluacion['procedimiento']);
        $this->assertSame([], $control->evaluacion['sistemas']);
        $this->assertSame('mensual', $control->evaluacion['frecuencia']);

        $edicion = $control->actualizaciones()->sole();
        $this->assertSame('edicion', $edicion->data['tipo']);
        $this->assertSame('Puntaje 6/10 · moderado', $edicion->diffLegible()['campos'][0]['despues']);
    }

    #[Test]
    public function sobre_un_control_aprobado_el_area_propone_y_no_mueve_el_residual_hasta_aprobar(): void
    {
        $control = $this->control('aprobado', numeroViejo: 2);
        $riesgo = $this->riesgoCon($control);
        $ajeno = $this->riesgoCon($control, $this->otraGerencia);
        $this->assertSame(10, $this->residual($riesgo));

        $this->contestar($this->empleado, $control, $this->cuestionarioCompleto())->assertHasNoErrors();

        $propuesta = $control->actualizaciones()->propuestasPendientes('campos')->sole();
        $this->assertSame(Estado::borrador()->id, $propuesta->estado_id);
        $this->assertSame(2, $control->fresh()->mitigacion_default);
        $this->assertSame(10, $this->residual($riesgo));

        $propuesta->marcarValidada($this->gerente);
        $this->assertSame(10, $this->residual($riesgo)); // validada todavía no se aplica

        $propuesta->fresh()->marcarAprobada($this->comite);

        $this->assertSame(10, $control->fresh()->mitigacion_default);
        $this->assertSame(2, $this->residual($riesgo));
        $this->assertSame(2, $this->residual($ajeno)); // vale igual en el riesgo de otra gerencia
    }

    #[Test]
    public function una_propuesta_pendiente_frena_otra_valoracion(): void
    {
        $control = $this->control('aprobado', 4);
        $this->contestar($this->empleado, $control, $this->cuestionarioCompleto());

        Livewire::actingAs($this->gerente)
            ->test(ValorControl::class, ['control' => $control])
            ->call('abrir')
            ->assertSet('abierto', false);

        Livewire::actingAs($this->gerente)
            ->test(ValorControl::class, ['control' => $control])
            ->set('abierto', true)
            ->set('cuestionario', $this->cuestionarioCompleto())
            ->call('guardar')
            ->assertHasErrors('cuestionario');

        $this->assertCount(1, $control->actualizaciones()->propuestasPendientes('campos')->get());
    }

    #[Test]
    public function el_comite_no_valora_controles(): void
    {
        $control = $this->control('aprobado', 4);

        Livewire::actingAs($this->comite)
            ->test(ValorControl::class, ['control' => $control])
            ->set('abierto', true)
            ->set('cuestionario', $this->cuestionarioCompleto())
            ->call('guardar')
            ->assertHasErrors('cuestionario');

        $this->assertSame(4, $control->fresh()->mitigacion_default);
        $this->assertCount(0, $control->actualizaciones);
    }

    #[Test]
    public function quien_no_gestiona_el_control_recibe_403(): void
    {
        $ajeno = User::factory()->gerente()->create(['area_id' => $this->otraGerencia->id]);
        $control = $this->control('borrador');

        Livewire::actingAs($ajeno)
            ->test(ValorControl::class, ['control' => $control])
            ->call('abrir')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(ValorControl::class, ['control' => $control])
            ->set('abierto', true)
            ->set('cuestionario', $this->cuestionarioCompleto())
            ->call('guardar')
            ->assertForbidden();

        $this->assertNull($control->fresh()->evaluacion);
    }

    // -------------------------------------------------------
    // Requisito para validar
    // -------------------------------------------------------

    #[Test]
    public function un_control_sin_cuestionario_no_se_valida(): void
    {
        $control = $this->control('borrador');

        $this->actingAs($this->gerente)
            ->post(route('auditoria.controles.validar', $control))
            ->assertSessionHas('error');
        $this->assertSame('borrador', $control->fresh()->estado->nombre);

        $validado = $this->control('validado', numeroViejo: 4);
        $this->actingAs($this->comite)
            ->post(route('auditoria.controles.aprobar', $validado))
            ->assertSessionHas('error');
        $this->assertSame('validado', $validado->fresh()->estado->nombre);

        $control->update(['evaluacion' => ControlFactory::evaluacion(5)]);
        $this->actingAs($this->gerente)->post(route('auditoria.controles.validar', $control));
        $this->assertSame('validado', $control->fresh()->estado->nombre);
    }

    #[Test]
    public function la_validacion_masiva_no_valida_un_control_sin_cuestionario(): void
    {
        $control = $this->control('borrador');

        $resultado = app(ValidacionMasivaService::class)->ejecutar($control, 'validar', [], [], $this->gerente);

        $this->assertArrayHasKey('error', $resultado);
        $this->assertSame('borrador', $control->fresh()->estado->nombre);
    }

    #[Test]
    public function en_la_cascada_de_un_riesgo_el_control_sin_cuestionario_queda_en_borrador(): void
    {
        $control = $this->control('borrador');
        $valorado = $this->control('borrador', 6);
        $riesgo = Riesgo::factory()->create(['area_id' => $this->gerencia->id, 'respuesta' => RespuestaRiesgo::Evitar]);
        $riesgo->controles()->attach([$control->id, $valorado->id]);
        $riesgo->objetivos()->attach(Objetivo::create([
            'nombre' => 'Objetivo de soporte',
            'estado_id' => Estado::aprobado()->id,
            'user_id' => $this->gerente->id,
            'area_id' => $this->gerencia->id,
        ])->id);

        $resultado = app(ValidacionMasivaService::class)->ejecutar(
            $riesgo, 'validar', [], [['tipo' => 'control', 'id' => $control->id], ['tipo' => 'control', 'id' => $valorado->id]], $this->gerente
        );

        $this->assertTrue($resultado['ok'] ?? false, json_encode($resultado));
        $this->assertSame('borrador', $control->fresh()->estado->nombre);
        $this->assertSame('validado', $valorado->fresh()->estado->nombre);
        $this->assertSame([$control->nombre], array_column($resultado['fallidos'], 'nombre'));
    }

    // -------------------------------------------------------
    // Ficha y alta
    // -------------------------------------------------------

    #[Test]
    public function el_alta_no_pide_cuestionario(): void
    {
        $this->actingAs($this->gerente)->post(route('auditoria.controles.store'), [
            'nombre' => 'Control nuevo',
            'area_id' => $this->gerencia->id,
        ])->assertRedirect();

        $this->assertNull(Control::where('nombre', 'Control nuevo')->sole()->evaluacion);
    }

    #[Test]
    public function editar_un_borrador_ajeno_devuelve_403_y_no_toca_nada(): void
    {
        $control = $this->control('borrador', 6);
        $ajeno = User::factory()->gerente()->create(['area_id' => $this->otraGerencia->id]);

        $this->actingAs($ajeno)
            ->put(route('auditoria.controles.update', $control), ['nombre' => 'X'])
            ->assertForbidden();

        $this->assertSame('Control de stock', $control->fresh()->nombre);
    }

    #[Test]
    public function la_ficha_del_control_devuelve_403_a_un_gerente_de_otra_gerencia(): void
    {
        $control = $this->control('aprobado', 6);
        $ajeno = User::factory()->gerente()->create(['area_id' => $this->otraGerencia->id]);

        Livewire::actingAs($ajeno)
            ->test(FichaControl::class, ['control' => $control])
            ->call('activarEdicion')
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(FichaControl::class, ['control' => $control])
            ->set('form', ['nombre' => 'X', 'descripcion' => null])
            ->set('mensaje', 'Intento ajeno')
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame('Control de stock', $control->fresh()->nombre);
    }

    // -------------------------------------------------------
    // Reevaluación del auditor
    // -------------------------------------------------------

    private function auditor(): User
    {
        return User::factory()->auditor()->create(['area_id' => $this->otraGerencia->id]);
    }

    private function reevaluar(User $user, Control $control, array $cuestionario, string $comentario = 'Se revisó una muestra de 30 operaciones.', array $archivos = [])
    {
        return Livewire::actingAs($user)
            ->test(ValorControl::class, ['control' => $control])
            ->call('abrirReevaluacion')
            ->set('cuestionario', $cuestionario)
            ->set('comentario', $comentario)
            ->set('archivos', $archivos)
            ->call('guardar');
    }

    #[Test]
    public function el_auditor_reevalua_un_control_aprobado_de_otra_area_en_el_acto(): void
    {
        Storage::fake('public');
        $auditor = $this->auditor();
        $control = $this->control('aprobado', 4);
        $riesgo = $this->riesgoCon($control);
        $this->assertSame(8, $this->residual($riesgo));

        $this->reevaluar($auditor, $control, $this->cuestionarioCompleto(), archivos: [UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf')])
            ->assertHasNoErrors()
            ->assertDispatched('control-actualizado');

        $this->assertSame(10, $control->fresh()->mitigacion_default);
        $this->assertSame(2, $this->residual($riesgo)); // sin gerente ni comité
        $this->assertCount(0, $control->actualizaciones()->propuestasPendientes()->get());

        $entrada = $control->actualizaciones()->where('data->origen', 'reevaluacion_auditor')->sole();
        $this->assertSame(Estado::aprobado()->id, $entrada->estado_id);
        $this->assertSame($auditor->id, $entrada->aprobado_por_id);
        $this->assertSame('Se revisó una muestra de 30 operaciones.', $entrada->mensaje);
        $this->assertSame('informe.pdf', $entrada->getFirstMedia('adjuntos')?->file_name);
        $this->assertFalse($entrada->esAprobacion()); // no admite carga de comité
        $this->assertSame('Puntaje 10/10 · suficiente', $entrada->diffLegible()['campos'][0]['despues']);
    }

    #[Test]
    public function la_reevaluacion_del_auditor_exige_comentario(): void
    {
        $control = $this->control('aprobado', 4);

        $this->reevaluar($this->auditor(), $control, $this->cuestionarioCompleto(), comentario: '')
            ->assertHasErrors('comentario');

        $this->assertSame(4, $control->fresh()->mitigacion_default);
    }

    #[Test]
    public function la_reevaluacion_del_auditor_no_pisa_una_propuesta_pendiente(): void
    {
        $control = $this->control('aprobado', 4);
        $this->contestar($this->empleado, $control, $this->cuestionarioCompleto(['respuestas' => ['detecta' => 0]]));

        Livewire::actingAs($this->auditor())
            ->test(ValorControl::class, ['control' => $control])
            ->set('modo', 'auditor')
            ->set('abierto', true)
            ->set('cuestionario', $this->cuestionarioCompleto())
            ->set('comentario', 'Reevaluación')
            ->call('guardar')
            ->assertHasErrors('cuestionario');

        $this->assertSame(4, $control->fresh()->mitigacion_default);
    }

    #[Test]
    public function el_auditor_no_reevalua_un_control_en_borrador_o_validado(): void
    {
        $auditor = $this->auditor();

        foreach (['borrador', 'validado'] as $estado) {
            $control = $this->control($estado, 4);
            $this->assertFalse($auditor->can('reevaluar', $control));

            Livewire::actingAs($auditor)
                ->test(ValorControl::class, ['control' => $control])
                ->call('abrirReevaluacion')
                ->assertForbidden();

            $this->assertSame(4, $control->fresh()->mitigacion_default);
        }

        // Tampoco propone por el circuito del área.
        Livewire::actingAs($auditor)
            ->test(ValorControl::class, ['control' => $this->control('validado', 4)])
            ->call('abrir')
            ->assertSet('abierto', false);
    }

    #[Test]
    public function quien_no_es_auditor_no_reevalua(): void
    {
        $control = $this->control('aprobado', 4);

        foreach ([$this->gerente, $this->comite, $this->empleado] as $usuario) {
            $this->assertFalse($usuario->can('reevaluar', $control));

            Livewire::actingAs($usuario)
                ->test(ValorControl::class, ['control' => $control])
                ->call('abrirReevaluacion')
                ->assertForbidden();

            Livewire::actingAs($usuario)
                ->test(ValorControl::class, ['control' => $control])
                ->set('modo', 'auditor')
                ->set('abierto', true)
                ->set('cuestionario', $this->cuestionarioCompleto())
                ->set('comentario', 'Intento')
                ->call('guardar')
                ->assertForbidden();
        }

        $this->assertSame(4, $control->fresh()->mitigacion_default);
        $this->assertCount(0, $control->actualizaciones);
    }
}
