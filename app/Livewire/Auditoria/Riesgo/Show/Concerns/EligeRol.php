<?php

namespace App\Livewire\Auditoria\Riesgo\Show\Concerns;

use App\Models\Auditoria\Actualizacion;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * Elegir con qué rol se guarda un cambio cuando el usuario es gerente y comité
 * (D-052): sobre algo aprobado de su subárbol, "Enviar como propuesta" (nace
 * validada, como cualquier gerente) o "Establecer como comité" (nace aprobada,
 * se aplica y guarda N° y fecha del comité). El botón de guardar llama a
 * abrirElegirRol() cuando requiereElegirRol(); el modal
 * (auditoria.partials.elegir-rol-modal) llama a guardarComo(), que termina en el
 * guardar() del componente. Requiere `$estadoModelo` y que el componente cargue
 * `$areaModelo` (Actualizacion::areaDe()) al renderizar.
 * Tests: DobleRolGerenteComiteTest.
 */
trait EligeRol
{
    public bool $eligiendoRol = false;

    public bool $comoComite = false;

    public array $comite = ['numero' => null, 'fecha' => null];

    #[Locked]
    public ?int $areaModelo = null;

    /** Si guardar tiene que preguntar con qué rol: doble rol, aprobado y de su subárbol. */
    public function requiereElegirRol(): bool
    {
        return Auth::user()->eligeRolEn($this->estadoModelo, $this->areaModelo);
    }

    public function abrirElegirRol(): void
    {
        $this->resetErrorBag('comite');
        $this->comite = ['numero' => null, 'fecha' => null];
        $this->eligiendoRol = true;
    }

    public function guardarComo(string $como): void
    {
        $this->comoComite = $como === 'comite' && $this->requiereElegirRol();
        if ($this->comoComite) {
            $this->validate(Actualizacion::reglasComite(), Actualizacion::mensajesComite());
        }
        $this->eligiendoRol = false;

        try {
            $this->guardar();
        } finally {
            $this->comoComite = false;
        }
    }

    /** Misma regla que Actualizacion::registrarCambioCampos(), para el texto, el botón y el guardado. */
    private function estadoParaActualizacion(): int
    {
        return Actualizacion::estadoInicialParaCambio(Auth::user(), $this->estadoModelo, $this->areaModelo, $this->comoComite);
    }

    /** Lo que se pasa como $comoComite a Actualizacion::registrar*(): null si es una propuesta. */
    private function comiteElegido(): ?array
    {
        return $this->comoComite && Auth::user()->esComite() ? $this->comite : null;
    }

    /** N° y fecha del comité para las actualizaciones que el componente crea a mano. */
    private function columnasComiteElegido(): array
    {
        return Actualizacion::columnasComiteSiAplica($this->estadoParaActualizacion(), $this->comiteElegido());
    }
}
