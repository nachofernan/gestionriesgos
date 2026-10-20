<?php

namespace App\Livewire\Auditoria\Riesgo\Show\Concerns;

use App\Models\Auditoria\Estado;
use App\Models\Auditoria\Riesgo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lo que comparten los bloques de riesgo/show (Ficha, Objetivos,
 * Controles, Planes) y de las otras pantallas de detalle (Fichas, Tareas del
 * plan) para mostrar debajo de lo vigente las propuestas de cambio
 * todavía sin aplicar, y para decirle al usuario qué va a pasar cuando guarde.
 * Requiere que el componente tenga `$estadoModelo` y `estadoParaActualizacion()`.
 */
trait PropuestasEnBloque
{
    /**
     * Qué pasa cuando este usuario guarda un cambio en el bloque: 'directo' (se
     * aplica en el acto) o 'propuesta' (queda pendiente de validación). Es la
     * misma regla que decide $aplicarAhora en guardar(), que también la consume,
     * para que la vista y el guardado no puedan contradecirse.
     * Test: el_modo_de_cambio_del_bloque_depende_del_estado_y_el_rol.
     */
    private function modoCambio(): string
    {
        if ($this->estadoModelo === 'borrador') {
            return 'directo';
        }

        $estadoId = $this->estadoParaActualizacion();
        $aplica = $estadoId === Estado::aprobado()->id
            || ($estadoId === Estado::validado()->id && $this->estadoModelo === 'validado');

        return $aplica ? 'directo' : 'propuesta';
    }

    /** Propuestas pendientes de la entidad que tocan `$parte` (relación o 'campos'), la más nueva primero. */
    private function propuestasDe(Model $entidad, string $parte): Collection
    {
        return $entidad->actualizaciones()
            ->propuestasPendientes($parte)
            ->with(['user', 'estado', 'validadoPor', 'actualizable'])
            ->latest('created_at')
            ->get();
    }

    /**
     * Para cada id de la relación, las marcas que las propuestas pendientes le
     * ponen a su fila vigente ("se propone quitar", "mit. 4 → 6").
     *
     * @return array<int, array<int, string>>
     */
    private function marcasDe(Collection $propuestas, string $relacion): array
    {
        $marcas = [];
        foreach ($propuestas as $propuesta) {
            $diff = $propuesta->data['diff']['relaciones'][$relacion] ?? [];
            foreach ($diff['quita'] ?? [] as $item) {
                $marcas[$item['id']][] = 'se propone quitar';
            }
            foreach ($diff['cambia'] ?? [] as $item) {
                $marcas[$item['id']][] = "mit. {$item['mitigacion_antes']} → {$item['mitigacion_despues']}";
            }
        }

        return $marcas;
    }

    /**
     * Ids de la relación que ya tienen una propuesta pendiente (alta, baja o
     * cambio de mitigación). Regla: un elemento no puede tener dos propuestas
     * pendientes a la vez; mientras tanto no se puede volver a proponer ni tocar
     * en el bloque (ver elementoConPropuesta()).
     *
     * @return array<int, int>
     */
    private function idsConPropuesta(Collection $propuestas, string $relacion): array
    {
        return $propuestas
            ->flatMap(function ($propuesta) use ($relacion) {
                $diff = $propuesta->data['diff']['relaciones'][$relacion] ?? [];

                return collect(array_merge($diff['agrega'] ?? [], $diff['quita'] ?? [], $diff['cambia'] ?? []))->pluck('id');
            })
            ->map(fn ($id) => (int) $id)
            ->unique()->values()->all();
    }

    /** Guarda de borde para agregar()/quitar()/actualizarMitigacion(): ver idsConPropuesta(). */
    private function elementoConPropuesta(string $relacion, int $id): bool
    {
        $entidad = $this->entidadDelBloque();

        return in_array($id, $this->idsConPropuesta($this->propuestasDe($entidad, $relacion), $relacion), true);
    }

    /**
     * La entidad dueña del bloque. Por defecto el riesgo de riesgo/show; los
     * bloques de otras pantallas (GestionTareas) la redefinen.
     */
    private function entidadDelBloque(): Model
    {
        return Riesgo::findOrFail($this->riesgoId);
    }

    /**
     * Convierte el diff del bloque en una propuesta por elemento, cada una con su
     * operación puntual ('agregar' / 'detach' / 'actualizar', ver
     * Actualizacion::aplicarOperacionesPorElemento()) en lugar de un 'sync' del
     * bloque entero: así cada alta, baja o cambio de mitigación se valida, aprueba
     * o rechaza por separado y no arrastra el estado del resto.
     * Tests: un_empleado_que_agrega_y_quita_controles_genera_una_propuesta_por_elemento,
     * un_empleado_que_agrega_y_quita_tareas_genera_una_propuesta_por_elemento.
     */
    private function proponerPorElemento(Model $entidad, string $relacion, array $diffRel, int $estadoId, string $sustantivo): void
    {
        $propuestas = [];
        foreach ($diffRel['agrega'] ?? [] as $item) {
            $pivot = array_key_exists('mitigacion', $item) ? ['mitigacion' => $item['mitigacion']] : [];
            $propuestas[] = ["Agregar {$sustantivo} «{$item['nombre']}»", ['agregar' => [$item['id'] => $pivot]], ['agrega' => [$item]]];
        }
        foreach ($diffRel['quita'] ?? [] as $item) {
            $propuestas[] = ["Quitar {$sustantivo} «{$item['nombre']}»", ['detach' => [$item['id']]], ['quita' => [$item]]];
        }
        foreach ($diffRel['cambia'] ?? [] as $item) {
            $propuestas[] = [
                "Mitigación de «{$item['nombre']}»: {$item['mitigacion_antes']} → {$item['mitigacion_despues']}",
                ['actualizar' => [$item['id'] => ['mitigacion' => $item['mitigacion_despues']]]],
                ['cambia' => [$item]],
            ];
        }

        DB::transaction(function () use ($entidad, $relacion, $propuestas, $estadoId) {
            foreach ($propuestas as [$mensaje, $ops, $diff]) {
                $entidad->actualizaciones()->create([
                    'user_id' => Auth::id(),
                    'mensaje' => $mensaje,
                    'estado_id' => $estadoId,
                    'data' => [
                        'tipo' => 'cambio',
                        'relaciones' => [$relacion => $ops],
                        'diff' => ['relaciones' => [$relacion => $diff]],
                    ],
                ]);
            }
        });
    }
}
