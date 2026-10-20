<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\RespuestaRiesgo;
use App\Enums\Auditoria\TipoArea;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Control;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\Auditoria\PlanAccion;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\Tarea;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El comité rechaza lo validado que le llega para aprobar (D-031): rechazo
 * definitivo, con motivo, en las cinco entidades. No rechaza borradores (eso es del
 * gerente) y no deja rechazar un plan u objetivo que un riesgo vigente necesita.
 */
class RechazoComiteTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $comite;

    private User $gerente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoRiesgoSeeder::class);

        $raiz = Area::create(['nombre' => 'Comité de Riesgo', 'tipo' => TipoArea::Gerencia]);
        $this->gerencia = Area::create(['nombre' => 'Gerencia Legales', 'area_padre_id' => $raiz->id, 'tipo' => TipoArea::Gerencia]);
        $this->comite = User::factory()->create(['rol' => 'comite', 'area_id' => $raiz->id]);
        $this->gerente = User::factory()->create(['rol' => 'gerente', 'area_id' => $this->gerencia->id]);
    }

    private function objetivo(string $estado): Objetivo
    {
        return Objetivo::create([
            'nombre' => 'Objetivo '.$estado,
            'estado_id' => Estado::where('nombre', $estado)->value('id'),
            'area_id' => $this->gerencia->id,
            'user_id' => $this->gerente->id,
        ]);
    }

    private function plan(string $estado): PlanAccion
    {
        return PlanAccion::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::where('nombre', $estado)->value('id')]);
    }

    /** @return array<string, Model> prefijo de ruta => elemento validado */
    private function elementosValidados(): array
    {
        $validado = Estado::validado()->id;

        return [
            'riesgos' => Riesgo::factory()->validado()->create(['area_id' => $this->gerencia->id, 'respuesta' => RespuestaRiesgo::Evitar]),
            'controles' => Control::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => $validado]),
            'objetivos' => $this->objetivo('validado'),
            'planes' => $this->plan('validado'),
            'tareas' => Tarea::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => $validado]),
        ];
    }

    #[Test]
    public function el_comite_rechaza_lo_validado_de_cualquier_tipo(): void
    {
        foreach ($this->elementosValidados() as $prefijo => $elemento) {
            $this->actingAs($this->comite)
                ->post(route("auditoria.{$prefijo}.rechazar", $elemento), ['motivo' => 'No cumple con la política'])
                ->assertSessionHasNoErrors();

            $elemento->refresh();
            $this->assertSame('borrado', $elemento->estado->nombre, "{$prefijo}: debería quedar rechazado");
            $registro = $elemento->actualizaciones()->latest('id')->first();
            $this->assertSame('rechazo', $registro->data['tipo']);
            $this->assertSame('No cumple con la política', $registro->motivo_rechazo);
            $this->assertEquals($this->comite->id, $registro->rechazado_por_id);
        }
    }

    #[Test]
    public function el_comite_no_rechaza_un_borrador(): void
    {
        $riesgo = Riesgo::factory()->borrador()->create(['area_id' => $this->gerencia->id]);

        $this->assertFalse($this->comite->can('rechazar', $riesgo));
        $this->actingAs($this->comite)
            ->post(route('auditoria.riesgos.rechazar', $riesgo), ['motivo' => 'Intento'])
            ->assertForbidden();
    }

    #[Test]
    public function un_gerente_no_rechaza_lo_validado(): void
    {
        foreach ($this->elementosValidados() as $prefijo => $elemento) {
            $this->assertFalse($this->gerente->can('rechazar', $elemento), $prefijo);
        }

        $tarea = Tarea::factory()->create(['area_id' => $this->gerencia->id, 'estado_id' => Estado::validado()->id]);
        $this->actingAs($this->gerente)
            ->post(route('auditoria.tareas.rechazar', $tarea), ['motivo' => 'Intento'])
            ->assertForbidden();
    }

    #[Test]
    public function no_se_rechaza_el_unico_plan_valido_de_un_riesgo_validado_que_mitiga(): void
    {
        $plan = $this->plan('validado');
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->gerencia->id, 'respuesta' => RespuestaRiesgo::Mitigar]);
        $riesgo->planesAccion()->attach($plan->id, ['mitigacion' => 2]);

        $this->actingAs($this->comite)
            ->post(route('auditoria.planes.rechazar', $plan), ['motivo' => 'No sirve'])
            ->assertSessionHas('error');
        $this->assertSame('validado', $plan->fresh()->estado->nombre);

        // Con otro plan vigente, el riesgo sigue cumpliendo y se puede rechazar.
        $riesgo->planesAccion()->attach($this->plan('aprobado')->id, ['mitigacion' => 2]);
        $this->actingAs($this->comite)
            ->post(route('auditoria.planes.rechazar', $plan), ['motivo' => 'No sirve'])
            ->assertSessionMissing('error');
        $this->assertSame('borrado', $plan->fresh()->estado->nombre);
    }

    #[Test]
    public function no_se_rechaza_el_unico_objetivo_valido_de_un_riesgo_validado(): void
    {
        $objetivo = $this->objetivo('validado');
        $riesgo = Riesgo::factory()->validado()->create(['area_id' => $this->gerencia->id, 'respuesta' => RespuestaRiesgo::Evitar]);
        $riesgo->objetivos()->attach($objetivo->id);

        $this->actingAs($this->comite)
            ->post(route('auditoria.objetivos.rechazar', $objetivo), ['motivo' => 'No aplica'])
            ->assertSessionHas('error');
        $this->assertSame('validado', $objetivo->fresh()->estado->nombre);
    }
}
