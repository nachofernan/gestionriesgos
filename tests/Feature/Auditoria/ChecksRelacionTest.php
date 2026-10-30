<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Control\Show\RiesgosControl;
use App\Livewire\Auditoria\Riesgo\Show\GestionControles;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Checks de cada relación control-riesgo (D-040): el número de la relación es su
 * puntaje sin pasar el número del control. Se cargan desde el riesgo
 * (GestionControles) o desde el panel del control (RiesgosControl), siempre con
 * la autorización sobre el riesgo y el circuito de propuestas de siempre.
 */
class ChecksRelacionTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private User $empleado;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);

        $this->control = Control::factory()->create([
            'nombre' => 'Control de stock',
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
            'checks' => ['documentado', 'responsable_asignado', 'frecuencia_definida'], // 6
        ]);
    }

    /** Riesgo de total 12 de la gerencia, asociado al control con $checks. */
    private function riesgo(string $estado = 'validado', ?array $checks = ['documentado']): Riesgo
    {
        $riesgo = Riesgo::factory()->create([
            'area_id' => $this->gerencia->id,
            'impacto' => 6,
            'probabilidad' => 6,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
            'respuesta' => RespuestaRiesgo::Evitar,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
        $riesgo->controles()->attach($this->control->id, $checks === null ? ['mitigacion' => null] : ['checks' => $checks]);

        return $riesgo;
    }

    private function pivot(Riesgo $riesgo)
    {
        return $riesgo->controles()->whereKey($this->control->id)->first()->pivot;
    }

    #[Test]
    public function una_relacion_con_checks_calcula_su_numero_al_guardar(): void
    {
        $riesgo = $this->riesgo(checks: ['documentado', 'deja_evidencia']);

        $this->assertSame(4, $this->pivot($riesgo)->mitigacion);
    }

    #[Test]
    public function el_numero_de_la_relacion_no_pasa_el_tope_aunque_se_tilde_todo(): void
    {
        $riesgo = $this->riesgo('borrador');

        $componente = Livewire::actingAs($this->gerente)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion');
        foreach (array_keys(Control::CHECKS) as $clave) {
            if ($clave !== 'documentado') {
                $componente->call('alternarCheck', $this->control->id, $clave);
            }
        }
        $this->assertSame(6, $componente->get('seleccionados')[0]['mitigacion']);

        $componente->call('guardar');

        $this->assertSame(6, $this->pivot($riesgo)->mitigacion);
        $this->assertCount(5, $this->pivot($riesgo)->checks);
    }

    #[Test]
    public function una_propuesta_vieja_no_pasa_el_tope_si_el_control_bajo(): void
    {
        $riesgo = $this->riesgo();

        Livewire::actingAs($this->empleado)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('alternarCheck', $this->control->id, 'responsable_asignado')
            ->call('alternarCheck', $this->control->id, 'frecuencia_definida')
            ->call('guardar');
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('controles')->sole();
        $this->assertSame(6, $propuesta->data['relaciones']['controles']['actualizar'][$this->control->id]['mitigacion']);

        // Mientras la propuesta espera, el control baja a 4.
        $this->control->update(['checks' => ['documentado', 'responsable_asignado']]);

        Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id, 'variante' => 'timeline'])
            ->call('validarActualizacion', $propuesta->id);

        $this->assertSame(4, $this->pivot($riesgo)->mitigacion);
    }

    #[Test]
    public function una_relacion_que_hereda_no_se_fija_al_guardar_sin_tocarla(): void
    {
        $riesgo = $this->riesgo('borrador', checks: null);

        Livewire::actingAs($this->gerente)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('guardar');

        $this->assertNull($this->pivot($riesgo)->mitigacion);
        $this->assertNull($this->pivot($riesgo)->checks);
    }

    #[Test]
    public function un_gerente_cambia_los_checks_de_una_relacion_desde_el_control(): void
    {
        $riesgo = $this->riesgo();

        Livewire::actingAs($this->gerente)
            ->test(RiesgosControl::class, ['control' => $this->control])
            ->call('editar', $riesgo->id)
            ->assertSet('checksEnEdicion', ['documentado'])
            ->call('alternarCheck', 'deja_evidencia')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('control-actualizado');

        // Gerente sobre un riesgo validado: se aplica en el acto.
        $this->assertSame(4, $this->pivot($riesgo)->mitigacion);
        $entrada = $riesgo->actualizaciones()->latest('id')->first();
        $this->assertNotNull($entrada->data['activated_by'] ?? null);
        $this->assertSame(
            ['mitigacion_antes' => 2, 'mitigacion_despues' => 4],
            array_intersect_key($entrada->data['diff']['relaciones']['controles']['cambia'][0], array_flip(['mitigacion_antes', 'mitigacion_despues']))
        );
    }

    #[Test]
    public function un_gerente_de_otra_gerencia_no_cambia_los_checks_desde_el_control(): void
    {
        $riesgo = $this->riesgo();
        $otra = Area::create(['nombre' => 'Gerencia Finanzas', 'tipo' => TipoArea::Gerencia]);
        $ajeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $otra->id]);

        Livewire::actingAs($ajeno)
            ->test(RiesgosControl::class, ['control' => $this->control])
            ->call('editar', $riesgo->id)
            ->assertForbidden();

        Livewire::actingAs($ajeno)
            ->test(RiesgosControl::class, ['control' => $this->control])
            ->set('editandoRiesgoId', $riesgo->id)
            ->set('checksEnEdicion', array_keys(Control::CHECKS))
            ->call('guardar')
            ->assertForbidden();

        $this->assertSame(2, $this->pivot($riesgo)->mitigacion);
    }

    #[Test]
    public function un_empleado_propone_los_checks_desde_el_control_y_se_aplican_al_validar(): void
    {
        $riesgo = $this->riesgo();

        Livewire::actingAs($this->empleado)
            ->test(RiesgosControl::class, ['control' => $this->control])
            ->call('editar', $riesgo->id)
            ->call('alternarCheck', 'responsable_asignado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, $this->pivot($riesgo)->mitigacion);
        $propuesta = $riesgo->actualizaciones()->propuestasPendientes('controles')->sole();
        $this->assertEquals('borrador', $propuesta->estado->nombre);

        // Mientras espera, desde el control no se vuelve a tocar.
        Livewire::actingAs($this->gerente)
            ->test(RiesgosControl::class, ['control' => $this->control])
            ->assertViewHas('editables', fn ($e) => $e[$riesgo->id] === false)
            ->call('editar', $riesgo->id)
            ->assertSet('editandoRiesgoId', null);

        Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id, 'variante' => 'timeline'])
            ->call('validarActualizacion', $propuesta->id);

        $this->assertSame(4, $this->pivot($riesgo)->mitigacion);
        $this->assertSame(['documentado', 'responsable_asignado'], $this->pivot($riesgo)->checks);
    }

    #[Test]
    public function en_un_riesgo_en_borrador_el_cambio_desde_el_control_se_aplica_directo(): void
    {
        $riesgo = $this->riesgo('borrador');

        Livewire::actingAs($this->empleado)
            ->test(RiesgosControl::class, ['control' => $this->control])
            ->call('editar', $riesgo->id)
            ->call('alternarCheck', 'documentado') // lo destilda: 0
            ->call('guardar');

        $this->assertSame(0, $this->pivot($riesgo)->mitigacion);
        $this->assertCount(0, Actualizacion::propuestasPendientes()->get());
        $this->assertSame('edicion', $riesgo->actualizaciones()->latest('id')->first()->data['tipo']);
    }
}
