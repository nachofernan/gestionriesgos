<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\RevisionInicial;
use App\Models\Auditoria\Riesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Revisión de la carga inicial (D-024): quién revisa y quién firma cada
 * elemento ante cada gerencia, y las reglas de la firma (errores bloquean,
 * advertencias exigen comentario, riesgo compartido firma cada gerencia).
 */
class RevisionInicialTest extends TestCase
{
    use RefreshDatabase;

    private Area $sistemas;

    private Area $coordinacion;

    private Area $finanzas;

    private User $gerenteSistemas;

    private User $coordinador;

    private User $empleadoCoordinacion;

    private User $gerenteFinanzas;

    private User $comite;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
        config(['auditoria.revision_inicial.activa' => true]);

        $raiz = Area::create(['nombre' => 'Comité de Riesgo', 'tipo' => TipoArea::Gerencia]);
        $this->sistemas = Area::create(['nombre' => 'Gerencia Sistemas', 'tipo' => TipoArea::Gerencia, 'area_padre_id' => $raiz->id]);
        $this->coordinacion = Area::create(['nombre' => 'Coordinación Infraestructura', 'area_padre_id' => $this->sistemas->id]);
        $this->finanzas = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia, 'area_padre_id' => $raiz->id]);

        $this->gerenteSistemas = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->sistemas->id]);
        $this->coordinador = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->coordinacion->id]);
        $this->empleadoCoordinacion = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->coordinacion->id]);
        $this->gerenteFinanzas = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->finanzas->id]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => $raiz->id]);
        $this->auditor = User::factory()->create(['rol' => 'auditor', 'area_id' => $this->finanzas->id]);
    }

    private function control(User $responsable): Control
    {
        return Control::factory()->create([
            'area_id' => $this->coordinacion->id,
            'user_id' => $responsable->id,
            'estado_id' => Estado::aprobado()->id,
        ]);
    }

    private function puede(User $user, string $accion, Model $elemento, Area $gerencia): bool
    {
        return $user->can($accion, [RevisionInicial::class, $elemento, $gerencia]);
    }

    #[Test]
    public function el_coordinador_revisa_un_elemento_de_su_area_pero_no_lo_firma(): void
    {
        $control = $this->control($this->empleadoCoordinacion);

        $this->assertTrue($this->puede($this->coordinador, 'revisar', $control, $this->sistemas));
        $this->assertTrue($this->puede($this->empleadoCoordinacion, 'revisar', $control, $this->sistemas));
        $this->assertFalse($this->puede($this->coordinador, 'firmar', $control, $this->sistemas));

        $revision = RevisionInicial::registrarRevisado($control, $this->sistemas, $this->coordinador, 'Visto con el equipo.');

        $this->assertEquals($this->coordinador->id, $revision->revisado_por_id);
        $this->assertNotNull($revision->revisado_en);
        $this->assertNull($revision->firmado_en);
    }

    #[Test]
    public function el_gerente_firma_en_su_gerencia_y_no_en_otra(): void
    {
        $control = $this->control($this->empleadoCoordinacion);

        $this->assertTrue($this->puede($this->gerenteSistemas, 'firmar', $control, $this->sistemas));
        $this->assertFalse($this->puede($this->gerenteFinanzas, 'firmar', $control, $this->sistemas));
        $this->assertFalse($this->puede($this->gerenteFinanzas, 'revisar', $control, $this->sistemas));
        // Finanzas no es una gerencia firmante de este control.
        $this->assertFalse($this->puede($this->gerenteFinanzas, 'firmar', $control, $this->finanzas));
    }

    #[Test]
    public function si_el_responsable_es_la_gerente_solo_ella_firma(): void
    {
        $control = $this->control($this->gerenteSistemas);

        $this->assertFalse($this->puede($this->coordinador, 'revisar', $control, $this->sistemas));
        $this->assertFalse($this->puede($this->gerenteSistemas, 'revisar', $control, $this->sistemas));
        $this->assertTrue($this->puede($this->gerenteSistemas, 'firmar', $control, $this->sistemas));
    }

    #[Test]
    public function el_gerente_firma_pero_no_revisa(): void
    {
        $control = $this->control($this->empleadoCoordinacion);

        $this->assertFalse($this->puede($this->gerenteSistemas, 'revisar', $control, $this->sistemas));
        $this->assertTrue($this->puede($this->gerenteSistemas, 'firmar', $control, $this->sistemas));
    }

    #[Test]
    public function el_comite_y_el_auditor_no_revisan_ni_firman(): void
    {
        $control = $this->control($this->empleadoCoordinacion);

        foreach ([$this->comite, $this->auditor] as $usuario) {
            $this->assertFalse($this->puede($usuario, 'revisar', $control, $this->sistemas));
            $this->assertFalse($this->puede($usuario, 'firmar', $control, $this->sistemas));
        }
    }

    #[Test]
    public function con_el_flag_apagado_nadie_revisa_ni_firma(): void
    {
        $control = $this->control($this->empleadoCoordinacion);
        config(['auditoria.revision_inicial.activa' => false]);

        $this->assertFalse($this->puede($this->coordinador, 'revisar', $control, $this->sistemas));
        $this->assertFalse($this->puede($this->gerenteSistemas, 'firmar', $control, $this->sistemas));
    }

    #[Test]
    public function no_se_firma_con_errores_abiertos(): void
    {
        $control = $this->control($this->empleadoCoordinacion);

        $this->expectException(ValidationException::class);

        try {
            RevisionInicial::registrarFirma($control, $this->sistemas, $this->gerenteSistemas, [
                ['codigo' => 'sin_area', 'severidad' => 'error', 'mensaje' => '...'],
            ], 'Igual lo firmo.');
        } finally {
            $this->assertDatabaseCount('revisiones_iniciales', 0);
        }
    }

    #[Test]
    public function firmar_con_advertencias_exige_comentario(): void
    {
        $control = $this->control($this->empleadoCoordinacion);
        $hallazgos = [['codigo' => 'mitigacion_en_cero', 'severidad' => 'advertencia', 'mensaje' => '...']];

        try {
            RevisionInicial::registrarFirma($control, $this->sistemas, $this->gerenteSistemas, $hallazgos);
            $this->fail('Firmó con advertencias sin comentario.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('comentario', $e->errors());
        }

        $revision = RevisionInicial::registrarFirma($control, $this->sistemas, $this->gerenteSistemas, $hallazgos, 'Lo aprobó el directorio así.');

        $this->assertEquals($this->gerenteSistemas->id, $revision->firmado_por_id);
        $this->assertSame(['mitigacion_en_cero'], $revision->advertencias_aceptadas);
        // El gerente firma aunque nadie lo haya marcado revisado.
        $this->assertNull($revision->revisado_en);
    }

    #[Test]
    public function firmar_con_un_arreglo_en_curso_no_pide_comentario_y_lo_registra(): void
    {
        $control = $this->control($this->empleadoCoordinacion);

        $revision = RevisionInicial::registrarFirma($control, $this->sistemas, $this->gerenteSistemas, [
            ['codigo' => 'sin_area', 'severidad' => 'en_curso', 'mensaje' => '...'],
        ]);

        $this->assertNotNull($revision->firmado_en);
        $this->assertSame(['sin_area'], $revision->arreglos_pendientes);
        $this->assertNull($revision->advertencias_aceptadas);
    }

    #[Test]
    public function no_se_revisa_ni_se_firma_dos_veces_lo_ya_firmado(): void
    {
        $control = $this->control($this->empleadoCoordinacion);
        RevisionInicial::registrarFirma($control, $this->sistemas, $this->gerenteSistemas, []);

        $this->expectException(ValidationException::class);
        RevisionInicial::registrarRevisado($control, $this->sistemas, $this->coordinador);
    }

    #[Test]
    public function un_riesgo_compartido_queda_firmado_cuando_firman_todas_sus_gerencias(): void
    {
        $riesgo = Riesgo::factory()->aprobado()->create(['area_id' => $this->sistemas->id, 'user_id' => $this->gerenteSistemas->id]);
        $riesgo->areas()->syncWithoutDetaching([$this->finanzas->id]);
        $riesgo->load('areas');

        // La responsable es gerente de Sistemas: eso no le quita a Finanzas su propia firma.
        $this->assertTrue($this->puede($this->gerenteFinanzas, 'firmar', $riesgo, $this->finanzas));
        $this->assertFalse($this->puede($this->gerenteFinanzas, 'firmar', $riesgo, $this->sistemas));
        $this->assertFalse($this->puede($this->gerenteSistemas, 'firmar', $riesgo, $this->finanzas));

        RevisionInicial::registrarFirma($riesgo, $this->sistemas, $this->gerenteSistemas, []);
        $this->assertFalse(RevisionInicial::firmadoPorTodas($riesgo->load('revisionesIniciales')));

        RevisionInicial::registrarFirma($riesgo, $this->finanzas, $this->gerenteFinanzas, []);
        $this->assertTrue(RevisionInicial::firmadoPorTodas($riesgo->load('revisionesIniciales')));
    }
}
