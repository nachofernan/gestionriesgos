<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Riesgo\Show\GestionControles;
use App\Livewire\Auditoria\Riesgo\Show\GestionObjetivos;
use App\Livewire\Auditoria\Riesgo\Show\GestionPlanes;
use App\Livewire\Auditoria\Riesgo\Show\ResumenPropuestas;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rediseño de riesgo/show: fuera del modo directo, cada alta, baja o cambio de
 * mitigación en Objetivos/Controles/Planes es su propia propuesta, con una
 * operación puntual ('agregar' / 'detach' / 'actualizar') en vez de un 'sync' del
 * bloque. Así se valida o rechaza cada elemento por separado, sin arrastrar al
 * resto. Un elemento con una propuesta pendiente no se puede volver a tocar hasta
 * resolverla. Cubre también el modo de cambio que anuncia cada bloque, el scope
 * propuestasPendientes, el banner de resumen y la resolución desde la tarjeta.
 */
class PropuestasPorElementoTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $gerente;

    private User $empleado;

    private Riesgo $riesgo;

    private Control $control1;

    private Control $control2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);

        // Total 12. Respuesta "evitar" para que Planes no exija conservar un plan.
        $this->riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $this->gerencia->id,
            'impacto' => 6,
            'probabilidad' => 6,
            'respuesta' => RespuestaRiesgo::Evitar,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);

        $this->control1 = $this->controlAprobado('Control uno');
        $this->control2 = $this->controlAprobado('Control dos');
        $this->riesgo->controles()->attach([
            $this->control1->id => ['mitigacion' => 3],
            $this->control2->id => ['mitigacion' => 4],
        ]);
    }

    private function controlAprobado(string $nombre): Control
    {
        return Control::factory()->create([
            'nombre' => $nombre,
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
        ]);
    }

    private function propuestas(): Collection
    {
        return $this->riesgo->actualizaciones()->propuestasPendientes()->orderBy('id')->get();
    }

    private function validarComo(User $user, Actualizacion $propuesta): void
    {
        Livewire::actingAs($user)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $this->riesgo->id, 'variante' => 'timeline'])
            ->call('validarActualizacion', $propuesta->id);
    }

    // -------------------------------------------------------
    // Una propuesta por elemento
    // -------------------------------------------------------

    #[Test]
    public function un_empleado_que_agrega_y_quita_controles_genera_una_propuesta_por_elemento(): void
    {
        $nuevo = $this->controlAprobado('Control nuevo');

        Livewire::actingAs($this->empleado)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('agregar', $nuevo->id)
            ->call('quitar', $this->control1->id)
            ->call('guardar');

        $propuestas = $this->propuestas();
        $this->assertCount(2, $propuestas);

        $ops = $propuestas->map(fn ($p) => $p->data['relaciones']['controles'])->all();
        $this->assertEquals(['agregar' => [$nuevo->id => ['mitigacion' => $nuevo->mitigacion_default]]], $ops[0]);
        $this->assertEquals(['detach' => [$this->control1->id]], $ops[1]);
        $this->assertTrue($propuestas->every(fn ($p) => $p->estado->nombre === 'borrador'));

        // Lo vigente no cambia hasta que se valide.
        $this->assertEqualsCanonicalizing(
            [$this->control1->id, $this->control2->id],
            $this->riesgo->refresh()->controles->pluck('id')->all()
        );
    }

    #[Test]
    public function validar_una_propuesta_aplica_solo_su_elemento_y_rechazar_otra_no_toca_nada(): void
    {
        $nuevo = $this->controlAprobado('Control nuevo');

        Livewire::actingAs($this->empleado)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('agregar', $nuevo->id)
            ->call('quitar', $this->control1->id)
            ->call('guardar');

        [$alta, $baja] = $this->propuestas()->all();

        $this->validarComo($this->gerente, $alta);

        // Se agrega el nuevo sin pisar al resto: control1 sigue (su baja está pendiente).
        $this->assertEqualsCanonicalizing(
            [$this->control1->id, $this->control2->id, $nuevo->id],
            $this->riesgo->refresh()->controles->pluck('id')->all()
        );
        $this->assertEquals('borrador', $baja->refresh()->estado->nombre);

        Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $this->riesgo->id, 'variante' => 'timeline'])
            ->call('rechazarActualizacion', $baja->id);

        $this->assertTrue($this->riesgo->refresh()->controles->pluck('id')->contains($this->control1->id));
        $this->assertEquals('borrado', $baja->refresh()->estado->nombre);
    }

    #[Test]
    public function un_cambio_de_mitigacion_propuesto_solo_toca_el_pivot_de_ese_control(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('actualizarMitigacion', $this->control1->id, 7)
            ->call('guardar');

        $propuesta = $this->propuestas()->sole();
        $this->assertEquals(
            ['actualizar' => [$this->control1->id => ['mitigacion' => 7]]],
            $propuesta->data['relaciones']['controles']
        );
        $this->assertEquals(5, $this->riesgo->refresh()->valor_residual); // 12 - 3 - 4, sin cambios todavía

        $this->validarComo($this->gerente, $propuesta);

        $controles = $this->riesgo->refresh()->controles->keyBy('id');
        $this->assertEquals(7, $controles[$this->control1->id]->pivot->mitigacion);
        $this->assertEquals(4, $controles[$this->control2->id]->pivot->mitigacion);
        $this->assertEquals(1, $this->riesgo->valor_residual); // 12 - 7 - 4
    }

    #[Test]
    public function un_control_con_mitigacion_default_no_genera_un_cambio_fantasma(): void
    {
        // Pivot en null: la mitigación efectiva es la default del control. Reguardar sin
        // tocar nada no puede proponer "cambió a N".
        $sinPivot = $this->controlAprobado('Control sin pivot');
        $this->riesgo->controles()->attach($sinPivot->id, ['mitigacion' => null]);

        Livewire::actingAs($this->empleado)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('guardar');

        $this->assertCount(0, $this->propuestas());
    }

    #[Test]
    public function un_elemento_con_propuesta_pendiente_no_se_puede_volver_a_tocar(): void
    {
        $nuevo = $this->controlAprobado('Control nuevo');

        Livewire::actingAs($this->empleado)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('agregar', $nuevo->id)
            ->call('quitar', $this->control1->id)
            ->call('guardar');

        $componente = Livewire::actingAs($this->gerente)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('quitar', $this->control1->id)          // su baja ya está propuesta
            ->call('actualizarMitigacion', $this->control1->id, 9)
            ->call('agregar', $nuevo->id);                 // su alta ya está propuesta

        $seleccionados = collect($componente->get('seleccionados'))->keyBy('id');
        $this->assertTrue($seleccionados->has($this->control1->id));
        $this->assertEquals(3, $seleccionados[$this->control1->id]['mitigacion']);
        $this->assertFalse($seleccionados->has($nuevo->id));

        $componente->call('guardar');
        $this->assertCount(2, $this->propuestas());
    }

    #[Test]
    public function en_un_riesgo_compartido_cada_propuesta_por_elemento_nace_con_el_voto_del_proponente(): void
    {
        $otraGerencia = Area::create(['nombre' => 'Gerencia Administración', 'tipo' => TipoArea::Gerencia]);
        $this->riesgo->areas()->syncWithoutDetaching([$otraGerencia->id]);
        $nuevo = $this->controlAprobado('Control nuevo');

        Livewire::actingAs($this->gerente)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('agregar', $nuevo->id)
            ->call('quitar', $this->control2->id)
            ->call('guardar');

        $propuestas = $this->propuestas();
        $this->assertCount(2, $propuestas);
        foreach ($propuestas as $propuesta) {
            $this->assertEquals('borrador', $propuesta->estado->nombre);
            $this->assertTrue($propuesta->validacionesGerencia()->where('area_id', $this->gerencia->id)->value('aprueba'));
            $this->assertNull($propuesta->validacionesGerencia()->where('area_id', $otraGerencia->id)->first());
        }
        $this->assertFalse($this->riesgo->refresh()->controles->pluck('id')->contains($nuevo->id));
    }

    #[Test]
    public function aplicar_operaciones_por_elemento_no_duplica_un_elemento_ya_asociado(): void
    {
        Actualizacion::aplicarOperacionesPorElemento($this->riesgo, 'controles', [
            'agregar' => [$this->control1->id => ['mitigacion' => 3]],
        ]);

        $this->assertEquals(1, $this->riesgo->controles()->where('controles.id', $this->control1->id)->count());
        $this->assertCount(2, $this->riesgo->refresh()->controles);
    }

    #[Test]
    public function quitar_un_objetivo_fuera_de_borrador_genera_su_propia_propuesta(): void
    {
        $objetivos = collect(['Objetivo A', 'Objetivo B'])->map(fn ($nombre) => Objetivo::create([
            'nombre' => $nombre,
            'estado_id' => Estado::aprobado()->id,
            'area_id' => $this->gerencia->id,
            'user_id' => $this->gerente->id,
        ]));
        $this->riesgo->objetivos()->attach($objetivos->pluck('id'));

        Livewire::actingAs($this->empleado)
            ->test(GestionObjetivos::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('quitar', $objetivos[0]->id)
            ->call('guardar');

        $propuesta = $this->propuestas()->sole();
        $this->assertEquals(['detach' => [$objetivos[0]->id]], $propuesta->data['relaciones']['objetivos']);
        $this->assertCount(2, $this->riesgo->refresh()->objetivos);

        $this->validarComo($this->gerente, $propuesta);

        $this->assertEquals([$objetivos[1]->id], $this->riesgo->refresh()->objetivos->pluck('id')->all());
    }

    #[Test]
    public function agregar_un_plan_con_mitigacion_se_propone_y_se_aplica_con_su_pivot(): void
    {
        $plan = PlanAccion::factory()->create([
            'area_id' => $this->gerencia->id,
            'estado_id' => Estado::aprobado()->id,
        ]);

        Livewire::actingAs($this->empleado)
            ->test(GestionPlanes::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('agregar', $plan->id)
            ->call('actualizarMitigacion', $plan->id, 5)
            ->call('guardar');

        $propuesta = $this->propuestas()->sole();
        $this->assertEquals(['agregar' => [$plan->id => ['mitigacion' => 5]]], $propuesta->data['relaciones']['planesAccion']);
        $this->assertCount(0, $this->riesgo->refresh()->planesAccion);

        $this->validarComo($this->gerente, $propuesta);

        $this->assertEquals(5, $this->riesgo->refresh()->planesAccion->sole()->pivot->mitigacion);
    }

    // -------------------------------------------------------
    // Modo de cambio que anuncia el bloque
    // -------------------------------------------------------

    #[Test]
    public function el_modo_de_cambio_del_bloque_depende_del_estado_el_rol_y_las_gerencias(): void
    {
        $comite = User::factory()->create(['rol' => 'comite', 'area_id' => null]);
        $modo = fn (User $user, Riesgo $riesgo) => Livewire::actingAs($user)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->viewData('modo');

        $borrador = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id]);
        $aprobado = Riesgo::factory()->aprobado()->create(['area_id' => $this->gerencia->id]);

        $this->assertEquals('directo', $modo($this->empleado, $borrador));
        $this->assertEquals('directo', $modo($this->gerente, $this->riesgo));   // gerente sobre validado
        $this->assertEquals('propuesta', $modo($this->empleado, $this->riesgo));
        $this->assertEquals('propuesta', $modo($this->gerente, $aprobado));     // espera al comité
        $this->assertEquals('directo', $modo($comite, $aprobado));

        $this->riesgo->areas()->syncWithoutDetaching([Area::create(['nombre' => 'Otra', 'tipo' => TipoArea::Gerencia])->id]);
        $this->assertEquals('doble', $modo($this->gerente, $this->riesgo->refresh()));
    }

    // -------------------------------------------------------
    // Scope propuestasPendientes
    // -------------------------------------------------------

    #[Test]
    public function propuestas_pendientes_incluye_solo_cambios_sin_aplicar_y_filtra_por_parte(): void
    {
        $crear = fn (string $estado, array $data) => $this->riesgo->actualizaciones()->create([
            'user_id' => $this->empleado->id,
            'mensaje' => 'x',
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
            'data' => $data,
        ]);
        $diffControl = ['diff' => ['relaciones' => ['controles' => ['quita' => [['id' => 1, 'nombre' => 'x']]]]]];
        $diffCampo = ['diff' => ['campos' => ['nombre' => ['antes' => 'a', 'despues' => 'b']]]];

        $enBorrador = $crear('borrador', ['tipo' => 'cambio'] + $diffControl);
        $esperaComite = $crear('validado', ['tipo' => 'cambio'] + $diffCampo);
        $crear('validado', ['tipo' => 'cambio', 'activated_by' => 'Alguien'] + $diffControl); // ya aplicada
        $crear('borrado', ['tipo' => 'cambio'] + $diffControl);                               // rechazada
        $crear('borrador', ['tipo' => 'edicion'] + $diffControl);                             // rastro de borrador
        Actualizacion::registrarNota($this->riesgo, $this->empleado, 'Una nota');

        $ids = fn (?string $parte) => $this->riesgo->actualizaciones()->propuestasPendientes($parte)->pluck('id')->sort()->values()->all();

        $this->assertEquals([$enBorrador->id, $esperaComite->id], $ids(null));
        $this->assertEquals([$enBorrador->id], $ids('controles'));
        $this->assertEquals([$esperaComite->id], $ids('campos'));
        $this->assertEquals([], $ids('objetivos'));
    }

    // -------------------------------------------------------
    // Banner de resumen y resolución desde la tarjeta
    // -------------------------------------------------------

    #[Test]
    public function el_resumen_cuenta_las_propuestas_y_las_que_puede_resolver_cada_usuario(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('quitar', $this->control1->id)
            ->call('actualizarMitigacion', $this->control2->id, 1)
            ->call('guardar');

        // El proponente las ve pero no las resuelve (sólo puede retirarlas).
        Livewire::actingAs($this->empleado)
            ->test(ResumenPropuestas::class, ['riesgo' => $this->riesgo])
            ->assertViewHas('total', 2)
            ->assertViewHas('resolvibles', 0)
            ->assertViewHas('porBloque', ['controles' => ['etiqueta' => 'Controles', 'cantidad' => 2]]);

        Livewire::actingAs($this->gerente)
            ->test(ResumenPropuestas::class, ['riesgo' => $this->riesgo])
            ->assertViewHas('resolvibles', 2);
    }

    #[Test]
    public function resolver_desde_la_tarjeta_pasa_por_la_autorizacion_de_cada_accion(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('quitar', $this->control1->id)
            ->call('guardar');
        $propuesta = $this->propuestas()->sole();

        $ajena = Area::create(['nombre' => 'Gerencia Ajena', 'tipo' => TipoArea::Gerencia]);
        $gerenteAjeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $ajena->id]);

        Livewire::actingAs($gerenteAjeno)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $this->riesgo->id, 'variante' => 'timeline'])
            ->dispatch('resolver-actualizacion', id: $propuesta->id, accion: 'validar')
            ->assertForbidden();
        $this->assertEquals('borrador', $propuesta->refresh()->estado->nombre);

        Livewire::actingAs($this->gerente)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $this->riesgo->id, 'variante' => 'timeline'])
            ->dispatch('resolver-actualizacion', id: $propuesta->id, accion: 'validar')
            ->assertDispatched('riesgo-actualizado');

        $this->assertEquals('validado', $propuesta->refresh()->estado->nombre);
        $this->assertFalse($this->riesgo->refresh()->controles->pluck('id')->contains($this->control1->id));
    }

    #[Test]
    public function el_proponente_retira_su_propuesta_desde_la_tarjeta(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(GestionControles::class, ['riesgo' => $this->riesgo])
            ->call('activarEdicion')
            ->call('quitar', $this->control1->id)
            ->call('guardar');
        $propuesta = $this->propuestas()->sole();

        Livewire::actingAs($this->empleado)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $this->riesgo->id, 'variante' => 'timeline'])
            ->dispatch('resolver-actualizacion', id: $propuesta->id, accion: 'cancelar');

        $this->assertEquals('borrado', $propuesta->refresh()->estado->nombre);
        $this->assertCount(0, $this->propuestas());
        $this->assertCount(2, $this->riesgo->refresh()->controles);
    }
}
