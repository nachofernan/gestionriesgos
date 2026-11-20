<?php

namespace App\Livewire\Auditoria\Objetivo\Show;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Objetivo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Tarjeta lateral de objetivo/show: la fecha objetivo como protagonista (cuánto
 * falta o hace cuánto venció), la clasificación estratégico/PEIS, los PEIS a los que
 * contribuye o, si es un PEIS, quiénes le contribuyen (D-054), y
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
        $user = Auth::user();
        // Los rechazados quedan en limbo y los que el usuario no puede ver no se listan.
        $vigentes = fn ($q) => $q->visiblePara($user)->whereNot('objetivos.estado_id', Estado::borrado()->id)->orderBy('objetivos.nombre');
        $objetivo = Objetivo::with([
            'area', 'user', 'estado',
            'peis' => $vigentes,
            'contribuyentes' => $vigentes, 'contribuyentes.area',
        ])->findOrFail($this->objetivoId);

        $dias = $objetivo->fecha_objetivo ? (int) now()->startOfDay()->diffInDays($objetivo->fecha_objetivo, false) : null;

        return view('livewire.auditoria.objetivo.show.info-objetivo', [
            'objetivo' => $objetivo,
            'dias' => $dias,
        ]);
    }
}
