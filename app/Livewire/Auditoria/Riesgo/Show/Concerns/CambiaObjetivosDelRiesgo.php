<?php

namespace App\Livewire\Auditoria\Riesgo\Show\Concerns;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\Auth;

/**
 * Cómo se registra un cambio en los objetivos de un riesgo, venga del bloque
 * Objetivos del riesgo (GestionObjetivos) o del bloque Riesgos del objetivo
 * (RiesgosObjetivo, D-055). Siempre es un cambio del riesgo: en borrador se
 * sincroniza y queda en su historial; fuera de borrador se aplica en el acto o
 * queda una propuesta por elemento, según modoCambio(). Requiere PropuestasEnBloque,
 * con `$estadoModelo` y `$areaModelo` del riesgo cargados.
 * Tests: un_objetivo_agrega_un_riesgo_en_borrador,
 * un_objetivo_propone_agregar_un_riesgo_validado.
 */
trait CambiaObjetivosDelRiesgo
{
    /**
     * Sincroniza `$ids` (todos los objetivos que el riesgo tiene que quedar teniendo)
     * o deja la propuesta. `$diffRel` es el agrega/quita que pinta el historial.
     * Devuelve qué pasó: 'borrador', 'directo' o 'propuesta'.
     */
    private function registrarCambioObjetivos(Riesgo $riesgo, array $ids, array $diffRel): string
    {
        if ($this->estadoModelo === 'borrador') {
            $riesgo->objetivos()->sync($ids);

            // Rastro en el historial también en borrador, si la selección cambió.
            if (! empty($diffRel)) {
                $riesgo->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => 'Objetivos asociados',
                    'estado_id' => Estado::borrador()->id,
                    'data' => ['tipo' => 'edicion', 'diff' => ['relaciones' => ['objetivos' => $diffRel]]],
                ]);
            }

            return 'borrador';
        }

        $estadoId = $this->estadoParaActualizacion();

        if ($this->modoCambio() === 'directo') {
            $data = ['tipo' => 'cambio', 'relaciones' => ['objetivos' => ['sync' => $ids]]];
            if (! empty($diffRel)) {
                $data['diff'] = ['relaciones' => ['objetivos' => $diffRel]];
            }
            // Se aplica en el acto: activated_by hace que el historial lo rotule
            // "Cambios aplicados" y no "Cambios propuestos".
            $data['activated_by'] = Auth::user()->name;
            $riesgo->objetivos()->sync($ids);
            $riesgo->actualizaciones()->create([
                'user_id' => Auth::id(),
                'mensaje' => 'Objetivos asociados actualizados',
                'estado_id' => $estadoId,
                'data' => $data,
            ] + $this->columnasComiteElegido());

            return 'directo';
        }

        // Fuera del modo directo, cada alta/baja es su propia propuesta.
        $this->proponerPorElemento($riesgo, 'objetivos', $diffRel, $estadoId, 'objetivo');

        return 'propuesta';
    }
}
