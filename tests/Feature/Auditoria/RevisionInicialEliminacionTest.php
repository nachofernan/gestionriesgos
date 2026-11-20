<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\RevisionInicial;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use App\Services\Auditoria\RevisionInicial\DetectorInconsistencias;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La firma de la revisión inicial tiene dos salidas (D-058): resolver las
 * inconsistencias o proponer el borrado. Con la eliminación propuesta todo queda
 * en curso y se firma; si después se rechaza una propuesta en la que se apoyó la
 * firma (la eliminación, o una asociación que arreglaba el otro lado), la firma se
 * anula y el elemento vuelve a la revisión.
 */
class RevisionInicialEliminacionTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private User $empleado;

    private User $comite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
        config(['auditoria.revision_inicial.activa' => true]);

        $this->gerencia = Area::create(['nombre' => 'Gerencia de Producción', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->gerente()->create(['area_id' => $this->gerencia->id]);
        $this->empleado = User::factory()->empleado()->create(['area_id' => $this->gerencia->id]);
        $this->comite = User::factory()->comite()->create(['area_id' => null]);
    }

    /** Riesgo aprobado sin objetivos: tiene al menos el error sin_objetivo. */
    private function riesgoInconsistente(): Riesgo
    {
        return Riesgo::factory()->create([
            'area_id' => $this->gerencia->id,
            'user_id' => $this->empleado->id,
            'estado_id' => Estado::aprobado()->id,
        ]);
    }

    private function hallazgos(Model $elemento, bool $contarBorradores = false): array
    {
        $cargado = DetectorInconsistencias::consulta($elemento::class)->findOrFail($elemento->id);

        return (new DetectorInconsistencias($contarBorradores))->detectar($cargado);
    }

    private function proponerEliminacion(User $user, Riesgo $riesgo): Actualizacion
    {
        $this->actingAs($user)->post(route('auditoria.riesgos.eliminacion', $riesgo), ['observaciones' => 'Ya se realizó.']);

        return Actualizacion::eliminacionPendienteDe($riesgo);
    }

    private function firma(Model $elemento): ?RevisionInicial
    {
        return RevisionInicial::where('revisable_type', $elemento->getMorphClass())->where('revisable_id', $elemento->id)->first();
    }

    #[Test]
    public function una_propuesta_de_eliminacion_deja_todo_en_curso_y_se_firma(): void
    {
        $riesgo = $this->riesgoInconsistente();
        $this->assertTrue(DetectorInconsistencias::tieneErrores($this->hallazgos($riesgo)));

        $this->proponerEliminacion($this->gerente, $riesgo);

        $hallazgos = collect($this->hallazgos($riesgo));
        $this->assertTrue($hallazgos->every(fn ($h) => $h['severidad'] === DetectorInconsistencias::EN_CURSO));

        RevisionInicial::validarYFirmar($riesgo, $this->gerencia, $this->gerente);

        $firma = $this->firma($riesgo);
        $this->assertNotNull($firma->firmado_en);
        $this->assertContains('sin_objetivo', $firma->arreglos_pendientes);
    }

    #[Test]
    public function una_eliminacion_en_borrador_no_cuenta_hasta_validarla(): void
    {
        $riesgo = $this->riesgoInconsistente();
        $propuesta = $this->proponerEliminacion($this->empleado, $riesgo);
        $this->assertSame('borrador', $propuesta->estado->nombre);

        $this->assertTrue(DetectorInconsistencias::tieneErrores($this->hallazgos($riesgo)));
        // La vista previa de "Validar y firmar" ya la cuenta.
        $this->assertFalse(DetectorInconsistencias::tieneErrores($this->hallazgos($riesgo, contarBorradores: true)));

        $validados = RevisionInicial::validarYFirmar($riesgo, $this->gerencia, $this->gerente);

        $this->assertSame(1, $validados);
        $this->assertSame('validado', $propuesta->fresh()->estado->nombre);
        $this->assertNotNull($this->firma($riesgo)->firmado_en);
    }

    #[Test]
    public function si_el_comite_rechaza_la_eliminacion_la_firma_se_anula(): void
    {
        $riesgo = $this->riesgoInconsistente();
        $propuesta = $this->proponerEliminacion($this->gerente, $riesgo);
        RevisionInicial::validarYFirmar($riesgo, $this->gerencia, $this->gerente);

        $this->actingAs($this->comite)
            ->post(route('auditoria.actualizaciones.rechazar', $propuesta), ['motivo' => 'Todavía aplica.'])
            ->assertSessionHasNoErrors();

        $firma = $this->firma($riesgo);
        $this->assertNull($firma->firmado_en);
        $this->assertNull($firma->arreglos_pendientes);
        $this->assertTrue(DetectorInconsistencias::tieneErrores($this->hallazgos($riesgo)));
        $this->assertTrue($riesgo->actualizaciones()->where('mensaje', 'like', 'Se anuló la firma de la revisión inicial%')->exists());
    }

    #[Test]
    public function si_el_comite_aprueba_la_eliminacion_el_riesgo_sale_borrado_y_la_firma_queda(): void
    {
        $riesgo = $this->riesgoInconsistente();
        $propuesta = $this->proponerEliminacion($this->gerente, $riesgo);
        RevisionInicial::validarYFirmar($riesgo, $this->gerencia, $this->gerente);

        $this->actingAs($this->comite)->post(route('auditoria.actualizaciones.aprobar', $propuesta));

        $this->assertSame('borrado', $riesgo->fresh()->estado->nombre);
        $this->assertNotNull($this->firma($riesgo)->firmado_en);
    }

    #[Test]
    public function si_el_comite_rechaza_la_asociacion_se_anula_la_firma_del_otro_lado(): void
    {
        $objetivo = Objetivo::create([
            'nombre' => 'Objetivo sin riesgos',
            'area_id' => $this->gerencia->id,
            'user_id' => $this->empleado->id,
            'estado_id' => Estado::aprobado()->id,
        ]);
        $riesgo = $this->riesgoInconsistente();
        // Asociar el riesgo al objetivo como propuesta validada que espera al comité.
        $propuesta = $riesgo->actualizaciones()->create([
            'user_id' => $this->gerente->id,
            'mensaje' => 'Asocia el objetivo.',
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'cambio', 'relaciones' => ['objetivos' => ['attach' => [$objetivo->id]]]],
        ]);

        $this->assertFalse(DetectorInconsistencias::tieneErrores($this->hallazgos($objetivo)));
        RevisionInicial::validarYFirmar($objetivo, $this->gerencia, $this->gerente);
        $this->assertNotNull($this->firma($objetivo)->firmado_en);

        $propuesta->marcarRechazada($this->comite, 'No corresponde.');

        $this->assertNull($this->firma($objetivo)->firmado_en);
        $this->assertTrue($objetivo->actualizaciones()->where('mensaje', 'like', 'Se anuló la firma%')->exists());
    }

    #[Test]
    public function un_rechazo_que_no_toca_los_arreglos_no_anula_la_firma(): void
    {
        $riesgo = $this->riesgoInconsistente();
        $this->proponerEliminacion($this->gerente, $riesgo);
        RevisionInicial::validarYFirmar($riesgo, $this->gerencia, $this->gerente);

        // Otra propuesta sobre el mismo riesgo, sin nada que ver con la firma.
        $otra = $riesgo->actualizaciones()->create([
            'user_id' => $this->gerente->id,
            'mensaje' => 'Cambia el nombre.',
            'estado_id' => Estado::validado()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['nombre' => 'Otro nombre']],
        ]);
        $otra->marcarRechazada($this->comite, 'No.');

        $this->assertNotNull($this->firma($riesgo)->firmado_en);
    }

    #[Test]
    public function la_card_de_la_ficha_ofrece_proponer_la_eliminacion_con_errores_abiertos(): void
    {
        $control = Control::factory()->create([
            'area_id' => $this->gerencia->id,
            'user_id' => $this->empleado->id,
            'estado_id' => Estado::aprobado()->id,
        ]);
        $riesgo = $this->riesgoInconsistente();

        $this->actingAs($this->gerente)->get(route('auditoria.riesgos.show', $riesgo))
            ->assertOk()
            ->assertSee('o proponé eliminarlo para poder firmar', false);

        $this->proponerEliminacion($this->gerente, $riesgo);

        $this->actingAs($this->gerente)->get(route('auditoria.riesgos.show', $riesgo))
            ->assertOk()
            ->assertDontSee('o proponé eliminarlo para poder firmar', false);

        $this->actingAs($this->gerente)->get(route('auditoria.controles.show', $control))
            ->assertOk()
            ->assertSee(route('auditoria.controles.eliminacion', $control), false);
    }
}
