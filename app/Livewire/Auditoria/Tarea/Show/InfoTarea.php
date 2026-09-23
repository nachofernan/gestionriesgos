<?php

namespace App\Livewire\Auditoria\Tarea\Show;

use App\Models\Auditoria\Tarea;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel de "Información" de una Tarea (avance, estado, fecha límite, etc.).
 * Antes era Blade estático dentro de tarea/show.blade.php; se extrae a su
 * propio componente para que se refresque solo cuando GestionActualizaciones
 * (el historial, en la misma pantalla) valida o aprueba un cambio — ver el
 * evento 'tarea-actualizado' que dispatcha, mismo mecanismo que InfoRiesgo.
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
        ]);
    }
}
