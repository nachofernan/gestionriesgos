<?php

namespace Tests\Feature\Auditoria;

use App\Enums\Auditoria\TipoArea;
use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Livewire\Auditoria\Riesgo\Show\ConversacionRiesgo;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use App\Models\Auditoria\TipoRiesgo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Conversación de riesgo/show: las notas (Actualizacion sin estado ni data, ver
 * Actualizacion::registrarNota()) viven separadas del historial de cambios. No
 * entran al ciclo de validación y la línea de tiempo de la Actividad no las muestra.
 */
class ConversacionRiesgoTest extends TestCase
{
    use RefreshDatabase;

    private Area $gerencia;

    private User $empleado;

    private Riesgo $riesgo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(EstadoRiesgoSeeder::class);

        $this->gerencia = Area::create(['nombre' => 'Gerencia Producción', 'tipo' => TipoArea::Gerencia]);
        $this->empleado = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->gerencia->id]);
        $this->riesgo = Riesgo::factory()->validado()->create([
            'area_id' => $this->gerencia->id,
            'tipo_riesgo_id' => TipoRiesgo::factory()->create()->id,
        ]);
    }

    #[Test]
    public function una_nota_de_la_conversacion_no_entra_al_ciclo_de_validacion(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(ConversacionRiesgo::class, ['riesgo' => $this->riesgo])
            ->set('mensaje', 'Hablé con el área, lo revisan el lunes')
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSet('mensaje', '')
            ->assertSet('enviadas', 1);

        $nota = $this->riesgo->actualizaciones()->sole();
        $this->assertNull($nota->estado_id);
        $this->assertNull($nota->data);
        $this->assertEquals($this->empleado->id, $nota->user_id);
        $this->assertCount(0, $this->riesgo->actualizaciones()->propuestasPendientes()->get());
    }

    #[Test]
    public function una_nota_puede_llevar_adjuntos(): void
    {
        Livewire::actingAs($this->empleado)
            ->test(ConversacionRiesgo::class, ['riesgo' => $this->riesgo])
            ->set('mensaje', 'Adjunto el acta')
            ->set('archivos', [UploadedFile::fake()->create('acta.pdf', 20, 'application/pdf')])
            ->call('enviar')
            ->assertHasNoErrors();

        $media = $this->riesgo->actualizaciones()->sole()->getMedia('adjuntos');
        $this->assertCount(1, $media);
        $this->assertEquals('acta.pdf', $media->first()->file_name);
    }

    #[Test]
    public function enviar_una_nota_devuelve_403_a_quien_no_gestiona_el_riesgo(): void
    {
        $ajena = Area::create(['nombre' => 'Gerencia Ajena', 'tipo' => TipoArea::Gerencia]);
        $gerenteAjeno = User::factory()->create(['rol' => 'gerente', 'area_id' => $ajena->id]);

        Livewire::actingAs($gerenteAjeno)
            ->test(ConversacionRiesgo::class, ['riesgo' => $this->riesgo])
            ->set('mensaje', 'Nota ajena')
            ->call('enviar')
            ->assertForbidden();

        $this->assertCount(0, $this->riesgo->actualizaciones()->get());
    }

    #[Test]
    public function la_linea_de_tiempo_de_la_actividad_no_muestra_las_notas(): void
    {
        $nota = Actualizacion::registrarNota($this->riesgo, $this->empleado, 'Una nota');
        $cambio = $this->riesgo->actualizaciones()->create([
            'user_id' => $this->empleado->id,
            'mensaje' => 'Cambio de nombre',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'cambio', 'campos' => ['nombre' => 'Otro']],
        ]);

        Livewire::actingAs($this->empleado)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $this->riesgo->id, 'variante' => 'timeline'])
            ->assertViewHas('actualizaciones', fn ($lista) => $lista->pluck('id')->all() === [$cambio->id])
            ->assertViewHas('todas', fn ($lista) => $lista->pluck('id')->contains($nota->id))
            ->assertViewHas('pendientesIds', fn ($ids) => $ids->all() === [$cambio->id]);

        // La variante completa (resto de las pantallas) sigue mostrando todo.
        Livewire::actingAs($this->empleado)
            ->test(GestionActualizaciones::class, ['modelType' => 'riesgo', 'modelId' => $this->riesgo->id])
            ->assertViewHas('actualizaciones', fn ($lista) => $lista->count() === 2);
    }
}
