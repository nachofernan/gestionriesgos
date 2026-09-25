<?php

namespace App\Livewire\Auditoria\Objetivo\Show;

use App\Models\Auditoria\Objetivo;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tarjeta lateral de objetivo/show: la fecha objetivo como protagonista (cuánto
 * falta o hace cuánto venció), la clasificación estratégico/PEIS con sus ítems y
 * quién lo gestiona. Se refresca con 'objetivo-actualizado' (FichaObjetivo o
 * GestionActualizaciones aplicaron un cambio), mismo mecanismo que InfoRiesgo.
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

        $dias = $objetivo->fecha_objetivo ? (int) now()->startOfDay()->diffInDays($objetivo->fecha_objetivo, false) : null;

        return view('livewire.auditoria.objetivo.show.info-objetivo', [
            'objetivo' => $objetivo,
            'dias' => $dias,
        ]);
    }
}
