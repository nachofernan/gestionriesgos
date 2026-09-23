<?php

namespace App\Livewire\Auditoria\Objetivo\Show;

use App\Models\Auditoria\Objetivo;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel de "Información" de un Objetivo (estado, fecha objetivo, clasificación
 * PEIS, etc.). Antes era Blade estático dentro de objetivo/show.blade.php; se
 * extrae a su propio componente para que se refresque solo cuando
 * GestionActualizaciones (el historial, en la misma pantalla) valida o aprueba
 * un cambio — ver el evento 'objetivo-actualizado' que dispatcha, mismo
 * mecanismo que InfoRiesgo.
 */
class InfoObjetivo extends Component
{
    public int $objetivoId;

    public function mount(Objetivo $objetivo): void
    {
        $this->objetivoId = $objetivo->id;
    }

    #[On('objetivo-actualizado')]
    public function refrescar(): void {}

    public function render()
    {
        $objetivo = Objetivo::with(['area', 'user', 'estado', 'peisItems'])->findOrFail($this->objetivoId);

        return view('livewire.auditoria.objetivo.show.info-objetivo', [
            'objetivo' => $objetivo,
        ]);
    }
}
