<?php

namespace App\Livewire\Auditoria\Tarea\Show;

use App\Models\Auditoria\Tarea;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tarjeta lateral de tarea/show: el avance como protagonista, la fecha límite
 * con cuánto falta o hace cuánto venció, y a quién está asignada. Se refresca con
 * 'tarea-actualizado' (FichaTarea o GestionActualizaciones aplicaron un cambio),
 * mismo mecanismo que InfoRiesgo.
 */
class InfoTarea extends Component
{
    public int $tareaId;

    public function mount(Tarea $tarea): void
    {
        $this->tareaId = $tarea->id;
    }

    #[On('tarea-actualizado')]
    public function refrescar(): void {}

    public function render()
    {
        $tarea = Tarea::with(['area', 'user', 'estado'])->findOrFail($this->tareaId);

        return view('livewire.auditoria.tarea.show.info-tarea', [
            'tarea' => $tarea,
            'tareaVencida' => $tarea->fecha && $tarea->fecha->lt(now()->startOfDay()) && $tarea->porcentaje_avance < 100,
            'dias' => $tarea->fecha ? (int) now()->startOfDay()->diffInDays($tarea->fecha, false) : null,
        ]);
    }
}
