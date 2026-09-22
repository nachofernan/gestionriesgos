<?php

namespace App\Livewire\Auditoria\Actualizaciones;

use App\Models\Auditoria\Actualizacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal liviano para validar/aprobar/rechazar una Actualizacion puntual desde la
 * pantalla de Pendientes, sin abrir el historial completo de la entidad
 * (GestionActualizaciones) ni pasar por el análisis de cascada de
 * ValidacionCascadaModal (una Actualizacion no tiene prerequisitos como sí
 * tienen Riesgo/Control/Objetivo/PlanAccion/Tarea). Al confirmar no redirige:
 * cierra y avisa por evento de navegador para que la fila de Pendientes se
 * marque como resuelta, dejando el resto del listado intacto.
 */
class AccionActualizacionModal extends Component
{
    public bool   $abierto         = false;
    public ?int   $actualizacionId = null;
    public string $accion          = '';
    public string $mensaje         = '';
    public string $entidadNombre   = '';
    public array  $diff            = [];
    public ?string $error          = null;

    #[On('abrir-accion-actualizacion')]
    public function abrir(int $actualizacionId, string $accion): void
    {
        $this->reset();

        $actualizacion = Actualizacion::with('actualizable')->find($actualizacionId);
        if (!$actualizacion || !Gate::forUser(Auth::user())->allows($accion, $actualizacion)) {
            return;
        }

        $this->actualizacionId = $actualizacion->id;
        $this->accion          = $accion;
        $this->mensaje         = $actualizacion->mensaje;
        $this->entidadNombre   = $actualizacion->actualizable?->nombre ?? '';
        $this->diff            = $actualizacion->data['diff']['campos'] ?? [];
        $this->abierto         = true;
    }

    public function confirmar(): void
    {
        $actualizacion = Actualizacion::find($this->actualizacionId);
        if (!$actualizacion || !Gate::forUser(Auth::user())->allows($this->accion, $actualizacion)) {
            $this->error = 'Ya no es posible realizar esta acción.';
            return;
        }

        match ($this->accion) {
            'validar'  => $actualizacion->marcarValidada(Auth::user()),
            'aprobar'  => $actualizacion->marcarAprobada(Auth::user()),
            'rechazar' => $actualizacion->marcarRechazada(Auth::user()),
        };

        $this->abierto = false;
        $this->dispatch('actualizacion-procesada', id: $actualizacion->id);
    }

    public function cancelar(): void
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.auditoria.actualizaciones.accion-actualizacion-modal');
    }
}
