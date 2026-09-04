<?php

namespace Tests\Feature\Auditoria;

use App\Livewire\Auditoria\Actualizaciones\GestionActualizaciones;
use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Area;
use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use App\Models\User;
use Database\Seeders\EstadoRiesgoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Carga de adjuntos en el historial de actualizaciones (colección `adjuntos` de
 * Actualizacion) y su descarga controlada por ruta, autorizada con el 'view' de
 * la entidad relacionada.
 *
 * Jerarquía:
 *   comite (raíz)
 *   ├── gerAdmin
 *   │   └── sectA  ← nacho (empleado)
 *   └── gerProd
 *       └── sectC  ← nocetti (empleado)
 */
class ActualizacionAdjuntoTest extends TestCase
{
    use RefreshDatabase;

    private Area $comite;

    private Area $gerAdmin;

    private Area $gerProd;

    private Area $sectA;

    private Area $sectC;

    private User $nacho;   // empleado – sectA

    private User $nocetti; // empleado – sectC

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(EstadoRiesgoSeeder::class);

        $this->comite = Area::create(['nombre' => 'Comité',     'area_padre_id' => null]);
        $this->gerAdmin = Area::create(['nombre' => 'Ger. Admin', 'area_padre_id' => $this->comite->id]);
        $this->gerProd = Area::create(['nombre' => 'Ger. Prod',  'area_padre_id' => $this->comite->id]);
        $this->sectA = Area::create(['nombre' => 'Sector A',   'area_padre_id' => $this->gerAdmin->id]);
        $this->sectC = Area::create(['nombre' => 'Sector C',   'area_padre_id' => $this->gerProd->id]);

        $this->nacho = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectA->id]);
        $this->nocetti = User::factory()->create(['rol' => 'empleado', 'area_id' => $this->sectC->id]);
    }

    // -------------------------------------------------------
    // Subida vía GestionActualizaciones
    // -------------------------------------------------------

    #[Test]
    public function crear_una_actualizacion_con_archivo_lo_adjunta_a_la_coleccion(): void
    {
        $objetivo = $this->objetivo($this->sectA);

        Livewire::actingAs($this->nacho)
            ->test(GestionActualizaciones::class, ['modelType' => 'objetivo', 'modelId' => $objetivo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Adjunto evidencia')
            ->set('archivos', [UploadedFile::fake()->create('evidencia.pdf', 200, 'application/pdf')])
            ->call('guardar')
            ->assertHasNoErrors();

        $actualizacion = Actualizacion::latest('id')->first();

        $this->assertNotNull($actualizacion);
        $this->assertCount(1, $actualizacion->getMedia('adjuntos'));
        $this->assertSame('evidencia.pdf', $actualizacion->getFirstMedia('adjuntos')->file_name);
    }

    #[Test]
    public function rechaza_un_archivo_de_tipo_no_permitido(): void
    {
        $objetivo = $this->objetivo($this->sectA);

        Livewire::actingAs($this->nacho)
            ->test(GestionActualizaciones::class, ['modelType' => 'objetivo', 'modelId' => $objetivo->id])
            ->call('abrirModal')
            ->set('mensaje', 'Adjunto ejecutable')
            ->set('archivos', [UploadedFile::fake()->create('script.exe', 10)])
            ->call('guardar')
            ->assertHasErrors('archivos.*');

        // La validación corta antes de crear la actualización.
        $this->assertDatabaseCount('actualizaciones', 0);
    }

    // -------------------------------------------------------
    // Descarga controlada por ruta
    // -------------------------------------------------------

    #[Test]
    public function usuario_con_permiso_de_ver_descarga_el_adjunto(): void
    {
        $objetivo = $this->objetivo($this->sectA); // validado → visible para todos
        $actualizacion = $this->actualizacionConAdjunto($objetivo);
        $media = $actualizacion->getFirstMedia('adjuntos');

        $this->actingAs($this->nacho)
            ->get(route('auditoria.actualizaciones.adjuntos.download', [$actualizacion, $media]))
            ->assertOk();
    }

    #[Test]
    public function usuario_sin_permiso_de_ver_recibe_403(): void
    {
        // Objetivo en borrador de otra gerencia: no es público y nacho no gestiona sectC.
        $objetivo = $this->objetivo($this->sectC, 'borrador');
        $actualizacion = $this->actualizacionConAdjunto($objetivo);
        $media = $actualizacion->getFirstMedia('adjuntos');

        $this->actingAs($this->nacho)
            ->get(route('auditoria.actualizaciones.adjuntos.download', [$actualizacion, $media]))
            ->assertForbidden();
    }

    #[Test]
    public function un_media_que_no_pertenece_a_la_actualizacion_da_404(): void
    {
        $actualizacionA = $this->actualizacionConAdjunto($this->objetivo($this->sectA));
        $actualizacionB = $this->actualizacionConAdjunto($this->objetivo($this->sectA));
        $mediaB = $actualizacionB->getFirstMedia('adjuntos');

        $this->actingAs($this->nacho)
            ->get(route('auditoria.actualizaciones.adjuntos.download', [$actualizacionA, $mediaB]))
            ->assertNotFound();
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function objetivo(Area $area, string $estado = 'validado'): Objetivo
    {
        return Objetivo::create([
            'nombre' => 'Objetivo test',
            'estado_id' => Estado::where('nombre', $estado)->firstOrFail()->id,
            'area_id' => $area->id,
            'user_id' => $this->nacho->id,
        ]);
    }

    private function actualizacionConAdjunto(Objetivo $objetivo): Actualizacion
    {
        $actualizacion = $objetivo->actualizaciones()->create([
            'user_id' => $this->nacho->id,
            'mensaje' => 'Con adjunto',
            'estado_id' => Estado::borrador()->id,
            'data' => ['tipo' => 'cambio'],
        ]);

        $actualizacion->addMediaFromString('contenido de prueba')
            ->usingFileName('archivo.pdf')
            ->toMediaCollection('adjuntos');

        return $actualizacion;
    }
}
