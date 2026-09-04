<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Riesgo\Show\GestionAreas;
use App\Livewire\Auditoria\Riesgo\Show\GestionControles;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use App\Policies\Auditoria\ActualizacionPolicy;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Doble validación de un riesgo compartido entre gerencias: cada propuesta de
 * cambio (campos vía GestionActualizaciones, gerencias vía GestionAreas) sobre un
 * riesgo con dos o más gerencias no se aplica hasta que todas las gerencias votan
 * a favor; un solo rechazo la tumba. Con una sola gerencia rige el flujo de siempre.
 */
class DobleValidacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);
    }

    private function gerencia(string $nombre): Area
    {
        return Area::create(['nombre' => $nombre, 'tipo' => TipoArea::Gerencia]);
    }

    private function gerente(Area $gerencia): User
    {
        return User::factory()->create(['rol' => 'gerente', 'area_id' => $gerencia->id]);
    }

    private function riesgoValidado(Area $gerencia): Riesgo
    {
        return Riesgo::factory()->validado()->create([
            'area_id' => $gerencia->id,
            'nombre' => 'Original',
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
    }

    /**
     * Riesgo validado compartido entre dos gerencias, con un gerente por cada una.
     *
     * @return array{riesgo: Riesgo, gerA: Area, gerB: Area, userA: User, userB: User}
     */
    private function riesgoCompartido(): array
    {
        $gerA = $this->gerencia('Gerencia A');
        $gerB = $this->gerencia('Gerencia B');
        $riesgo = $this->riesgoValidado($gerA);
        $riesgo->areas()->syncWithoutDetaching([$gerB->id]);

        return [
            'riesgo' => $riesgo,
            'gerA' => $gerA,
            'gerB' => $gerB,
            'userA' => $this->gerente($gerA),
            'userB' => $this->gerente($gerB),
        ];
    }

    private function proponerCambioNombre(User $user, Riesgo $riesgo, string $nombre): void
    {
        Livewire::actingAs($user)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Propuesta de cambio de nombre')
            ->set('cambios.nombre', $nombre)
            ->call('guardar');
    }

    #[Test]
    public function un_cambio_de_campo_no_se_aplica_hasta_que_todas_las_gerencias_validan(): void
    {
        ['riesgo' => $riesgo, 'gerA' => $gerA, 'gerB' => $gerB, 'userA' => $userA, 'userB' => $userB] = $this->riesgoCompartido();

        $this->proponerCambioNombre($userA, $riesgo, 'Nuevo Nombre');

        // El proponente votó a favor por su gerencia, pero el cambio no se aplica.
        $riesgo->refresh();
        $this->assertEquals('Original', $riesgo->nombre);
        $act = $riesgo->actualizaciones()->latest('created_at')->first();
        $this->assertEquals('borrador', $act->estado->nombre);
        $this->assertTrue($act->validacionesGerencia()->where('area_id', $gerA->id)->value('aprueba'));
        $this->assertNull($act->validacionesGerencia()->where('area_id', $gerB->id)->first());

        // La otra gerencia valida → se completa y se aplica.
        Livewire::actingAs($userB)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $act->id);

        $this->assertEquals('Nuevo Nombre', $riesgo->refresh()->nombre);
        $this->assertEquals('validado', $act->refresh()->estado->nombre);
    }

    #[Test]
    public function un_rechazo_de_una_gerencia_tumba_el_cambio(): void
    {
        ['riesgo' => $riesgo, 'userA' => $userA, 'userB' => $userB] = $this->riesgoCompartido();

        $this->proponerCambioNombre($userA, $riesgo, 'Nuevo Nombre');
        $act = $riesgo->actualizaciones()->latest('created_at')->first();

        Livewire::actingAs($userB)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('rechazarActualizacion', $act->id);

        $this->assertEquals('Original', $riesgo->refresh()->nombre);
        $this->assertEquals('borrado', $act->refresh()->estado->nombre);
    }

    #[Test]
    public function con_una_sola_gerencia_el_cambio_se_aplica_al_instante_sin_votos(): void
    {
        $ger = $this->gerencia('Gerencia Única');
        $riesgo = $this->riesgoValidado($ger);
        $user = $this->gerente($ger);

        $this->proponerCambioNombre($user, $riesgo, 'Nuevo Nombre');

        $this->assertEquals('Nuevo Nombre', $riesgo->refresh()->nombre);
        $act = $riesgo->actualizaciones()->latest('created_at')->first();
        $this->assertEquals('validado', $act->estado->nombre);
        $this->assertCount(0, $act->validacionesGerencia);
    }

    #[Test]
    public function agregar_la_segunda_gerencia_se_aplica_con_la_sola_validacion_del_proponente(): void
    {
        $gerA = $this->gerencia('Gerencia A');
        $gerB = $this->gerencia('Gerencia B');
        $riesgo = $this->riesgoValidado($gerA);
        $userA = $this->gerente($gerA);

        Livewire::actingAs($userA)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $gerB->id)
            ->call('guardar');

        // Pasar de 1 a 2 gerencias se aplica de una (el padrón previo era una sola).
        $this->assertTrue($riesgo->refresh()->areas->pluck('id')->contains($gerB->id));
        $this->assertFalse($riesgo->actualizaciones()->where('estado_id', Estado::borrador()->id)->exists());
    }

    #[Test]
    public function cambiar_gerencias_de_un_riesgo_ya_compartido_requiere_doble_validacion(): void
    {
        ['riesgo' => $riesgo, 'userA' => $userA, 'userB' => $userB] = $this->riesgoCompartido();
        $gerC = $this->gerencia('Gerencia C');

        Livewire::actingAs($userA)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $gerC->id)
            ->call('guardar');

        // Todavía no se sumó: la propuesta quedó pendiente.
        $this->assertFalse($riesgo->refresh()->areas->pluck('id')->contains($gerC->id));
        $act = $riesgo->actualizaciones()->latest('created_at')->first();
        $this->assertEquals('borrador', $act->estado->nombre);

        Livewire::actingAs($userB)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $act->id);

        $this->assertTrue($riesgo->refresh()->areas->pluck('id')->contains($gerC->id));
    }

    #[Test]
    public function no_se_pueden_cambiar_gerencias_con_una_propuesta_pendiente(): void
    {
        ['riesgo' => $riesgo, 'userA' => $userA] = $this->riesgoCompartido();
        $gerC = $this->gerencia('Gerencia C');

        // Deja una propuesta de campo pendiente.
        $this->proponerCambioNombre($userA, $riesgo, 'Nuevo Nombre');

        Livewire::actingAs($userA)
            ->test(GestionAreas::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $gerC->id)
            ->call('guardar')
            ->assertSet('error', 'Hay una propuesta pendiente de validación en este riesgo. Resolvela antes de cambiar las gerencias.');

        $this->assertFalse($riesgo->refresh()->areas->pluck('id')->contains($gerC->id));
    }

    #[Test]
    public function asociar_un_control_a_un_riesgo_compartido_queda_pendiente_hasta_que_todas_validan(): void
    {
        ['riesgo' => $riesgo, 'gerA' => $gerA, 'userA' => $userA, 'userB' => $userB] = $this->riesgoCompartido();
        $control = Control::factory()->create(['area_id' => $gerA->id]);

        Livewire::actingAs($userA)
            ->test(GestionControles::class, ['riesgo' => $riesgo])
            ->call('activarEdicion')
            ->call('agregar', $control->id)
            ->call('guardar');

        // La asociación no se aplica: nace pendiente y el proponente ya votó a favor.
        $this->assertFalse($riesgo->refresh()->controles->pluck('id')->contains($control->id));
        $act = $riesgo->actualizaciones()->latest('created_at')->first();
        $this->assertEquals('borrador', $act->estado->nombre);
        $this->assertTrue($act->validacionesGerencia()->where('area_id', $gerA->id)->value('aprueba'));

        // La otra gerencia valida → se aplica el control.
        Livewire::actingAs($userB)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $riesgo->id])
            ->call('validarActualizacion', $act->id);

        $this->assertTrue($riesgo->refresh()->controles->pluck('id')->contains($control->id));
    }

    #[Test]
    public function el_proponente_no_puede_validar_ni_rechazar_su_propia_propuesta(): void
    {
        ['riesgo' => $riesgo, 'userA' => $userA, 'userB' => $userB] = $this->riesgoCompartido();

        $this->proponerCambioNombre($userA, $riesgo, 'Nuevo Nombre');
        $act = $riesgo->actualizaciones()->latest('created_at')->first();
        $policy = new ActualizacionPolicy;

        // El proponente ya votó a favor al proponer: no le quedan validar ni rechazar.
        $this->assertFalse($policy->validar($userA, $act));
        $this->assertFalse($policy->rechazar($userA, $act));

        // La gerencia que todavía no votó sí puede resolver.
        $this->assertTrue($policy->validar($userB, $act));
        $this->assertTrue($policy->rechazar($userB, $act));
    }

    #[Test]
    public function un_gerente_de_una_gerencia_no_asociada_no_puede_validar_la_propuesta(): void
    {
        ['riesgo' => $riesgo, 'userA' => $userA] = $this->riesgoCompartido();
        $gerX = $this->gerencia('Gerencia Ajena');
        $userX = $this->gerente($gerX);

        $this->proponerCambioNombre($userA, $riesgo, 'Nuevo Nombre');
        $act = $riesgo->actualizaciones()->latest('created_at')->first();

        $this->assertFalse((new ActualizacionPolicy)->validar($userX, $act));
    }
}
