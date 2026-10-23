<?php

namespace App\Livewire\Auditoria\Riesgo\Show;

use App\Models\Auditoria\Riesgo;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Banner de riesgo/show con el resumen de las propuestas de cambio pendientes:
 * cuántas hay, cuántas puede resolver el usuario actual (mismas policies y
 * mismas condiciones de estado que muestran los botones en cada bloque) y en qué
 * bloque está cada una, con anclas. Se refresca con 'riesgo-actualizado'.
 */
class ResumenPropuestas extends Component
{
    public int $riesgoId;

    #[On('riesgo-actualizado')]
    public function refrescar(): void {}

    public function mount(Riesgo $riesgo): void
    {
        $this->riesgoId = $riesgo->id;
    }

    public function render()
    {
        $riesgo = Riesgo::with('estado')->findOrFail($this->riesgoId);
        $estado = $riesgo->estado?->nombre;
        $user = Auth::user();

        $propuestas = $riesgo->actualizaciones()->propuestasPendientes()->with(['estado', 'actualizable'])->get();

        $resolvibles = $propuestas->filter(fn ($p) => ($estado !== 'borrador' && ($user->can('validar', $p) || $user->can('rechazar', $p)))
            || ($estado === 'aprobado' && $user->can('aprobar', $p))
        )->count();

        // Bloque (ancla) al que pertenece cada parte que tocan las propuestas.
        $bloques = [
            'campos' => ['ficha', 'Datos'],
            'sedes' => ['ficha', 'Datos'],
            'dimensiones' => ['ficha', 'Datos'],
            'objetivos' => ['objetivos', 'Objetivos'],
            'controles' => ['controles', 'Controles'],
            'planesAccion' => ['planes', 'Planes de acción'],
        ];
        $porBloque = [];
        foreach ($propuestas as $propuesta) {
            $diff = $propuesta->data['diff'] ?? [];
            $partes = array_merge(isset($diff['campos']) ? ['campos'] : [], array_keys($diff['relaciones'] ?? []));
            // Una propuesta cuenta una vez por bloque: la de dimensiones trae también
            // su fundamento (campos) y los dos caen en la ficha.
            $bloquesDeLaPropuesta = collect($partes)->filter(fn ($p) => isset($bloques[$p]))->map(fn ($p) => $bloques[$p])->unique(0);
            foreach ($bloquesDeLaPropuesta as [$ancla, $etiqueta]) {
                $porBloque[$ancla] ??= ['etiqueta' => $etiqueta, 'cantidad' => 0];
                $porBloque[$ancla]['cantidad']++;
            }
        }

        return view('livewire.auditoria.riesgo.show.resumen-propuestas', [
            'total' => $propuestas->count(),
            'resolvibles' => $resolvibles,
            'porBloque' => $porBloque,
        ]);
    }
}
