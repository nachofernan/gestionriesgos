<?php

namespace App\Livewire\Auditoria\Modal;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de resumen rápido de un Riesgo, abierto vía el evento global 'ver-riesgo'
 * (ej. desde una fila de listado o un modal de otra entidad relacionada).
 */
class DetalleRiesgo extends Component
{
    public bool $abierto = false;

    public ?Riesgo $riesgo = null;

    /**
     * controles/objetivos/planesAccion filtrados por visibilidad (axioma 1 +
     * scopeVisiblePara) y sin los "borrado" (limbo, nunca se muestran), mismo
     * criterio que GestionControles/GestionObjetivos/GestionPlanes en el show del
     * riesgo.
     */
    #[On('ver-riesgo')]
    public function abrir(int $id): void
    {
        $user = Auth::user();
        $vigenteYVisible = fn ($q) => $q->visiblePara($user)->whereNot('estado_id', Estado::borrado()->id);

        $this->riesgo = Riesgo::with([
            'estado',
            'tipoRiesgo',
            'area',
            'controles' => $vigenteYVisible,
            'controles.estado',
            'objetivos' => $vigenteYVisible,
            'objetivos.estado',
            'planesAccion' => $vigenteYVisible,
            'planesAccion.estado',
            'planesAccion.tareas.estado',
        ])->find($id);

        if ($this->riesgo) {
            $this->riesgo->setRelation('controles', Estado::ordenarColeccion($this->riesgo->controles));
            $this->riesgo->setRelation('objetivos', Estado::ordenarColeccion($this->riesgo->objetivos));

            $planes = Estado::ordenarColeccion($this->riesgo->planesAccion);
            $planes->each(fn ($p) => $p->setRelation('tareas', Estado::ordenarColeccion($p->tareas)));
            $this->riesgo->setRelation('planesAccion', $planes);
        }

        $this->abierto = (bool) $this->riesgo;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->riesgo = null;
    }

    public function render()
    {
        return view('livewire.auditoria.modal.detalle-riesgo');
    }
}
