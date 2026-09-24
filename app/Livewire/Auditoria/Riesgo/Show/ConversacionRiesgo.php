<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Espacio de trabajo de riesgo/show: la conversación sobre el riesgo (notas,
 * que son Actualizaciones sin estado ni data, ver Actualizacion::registrarNota())
 * y todos los archivos adjuntos a cualquier actualización del riesgo en un solo
 * lugar. Separado de la Actividad (GestionActualizaciones, variante timeline),
 * que es el historial de cambios y ya no muestra las notas.
 */
class ConversacionRiesgo extends Component
{
    use WithFileUploads;

    public int $riesgoId;

    /** Pestaña visible: 'mensajes' | 'archivos'. */
    public string $pestana = 'mensajes';

    public string $mensaje = '';

    /** Notas registradas en esta sesión del componente: la vista la usa como wire:key del textarea para vaciarlo. */
    public int $enviadas = 0;

    /** Adjuntos temporales de Livewire para la nota que se está escribiendo. */
    public array $archivos = [];

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
    }

    /**
     * Publica una nota (con adjuntos opcionales). Mismas reglas de archivo que
     * GestionActualizaciones::guardar(). Muta: autoriza 'update' sobre el riesgo.
     * Tests: una_nota_de_la_conversacion_no_entra_al_ciclo_de_validacion,
     * enviar_una_nota_devuelve_403_a_quien_no_gestiona_el_riesgo.
     */
    public function enviar(): void
    {
        $riesgo = Riesgo::findOrFail($this->riesgoId);
        $this->authorize('update', $riesgo);

        $this->validate([
            'mensaje' => 'required|string|min:2',
            'archivos.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ], ['mensaje.required' => 'Escribí algo antes de enviar.']);

        $nota = Actualizacion::registrarNota($riesgo, Auth::user(), $this->mensaje);

        // Los medios van después de crear la nota (mueven archivos en disco).
        foreach ($this->archivos as $archivo) {
            $nota->addMedia($archivo->getRealPath())
                ->usingFileName($archivo->getClientOriginalName())
                ->toMediaCollection('adjuntos');
        }

        $this->reset(['mensaje', 'archivos']);
        $this->enviadas++;
    }

    public function quitarArchivo(int $indice): void
    {
        unset($this->archivos[$indice]);
        $this->archivos = array_values($this->archivos);
    }

    public function render()
    {
        $riesgo = Riesgo::findOrFail($this->riesgoId);

        $actualizaciones = $riesgo->actualizaciones()
            ->with(['user.area', 'media'])
            ->reorder('id')
            ->get();

        // Registro formal: la nota más reciente primero.
        $notas = $actualizaciones->filter(fn ($a) => $a->estado_id === null && empty($a->data))->sortByDesc('id')->values();

        // Todos los archivos del riesgo, vengan de una nota o de una propuesta, el más nuevo primero.
        $archivos = $actualizaciones
            ->flatMap(fn ($a) => $a->getMedia('adjuntos')->map(fn ($m) => ['media' => $m, 'actualizacion' => $a]))
            ->sortByDesc(fn ($x) => $x['media']->created_at)
            ->values();

        return view('livewire.auditoria.riesgo.show.conversacion-riesgo', [
            'notas' => $notas,
            'archivosRiesgo' => $archivos,
            'puedeEscribir' => Auth::user()->can('update', $riesgo),
        ]);
    }
}
