<?php

namespace App\Http\Controllers\Auditoria\Concerns;

use App\Models\Auditoria\Actualizacion;
use App\Models\Auditoria\PlanAccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Eliminación por propuesta de control, objetivo, plan de acción y tarea (D-047,
 * D-058), igual que la del riesgo pero sin dependientes: motivo obligatorio,
 * adjunto opcional y, con doble rol (D-052), "establecer como coordinador(a) del
 * comité" con N° y fecha. La usan los proponerEliminacion() de esos controladores,
 * que ya autorizaron con la Policy; datosEliminacion() la consumen sus show().
 * Tests: EliminacionPorPropuestaTest.
 */
trait ProponeEliminacion
{
    /**
     * Registra la propuesta (Actualizacion::registrarEliminacion()) y redirige: al
     * listado si el comité la aplicó en el acto, a la ficha si queda pendiente.
     */
    protected function registrarPropuestaEliminacion(Request $request, Model $entidad, string $prefijoRuta, string $eliminado): RedirectResponse
    {
        $data = $request->validate([
            'observaciones' => 'required|string|min:3|max:5000',
            'adjunto' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ], [
            'observaciones.required' => 'Contá por qué se elimina.',
            'observaciones.min' => 'Contá por qué se elimina.',
        ]);

        $user = Auth::user();
        $comoComite = null;
        if ($request->input('como') === 'comite' && $user->esComite()) {
            $comoComite = $request->validate(Actualizacion::reglasComite(), Actualizacion::mensajesComite())['comite'] ?? [];
        }

        $propuesta = Actualizacion::registrarEliminacion($entidad, $user, $data['observaciones'], null, $comoComite);

        if ($archivo = $request->file('adjunto')) {
            $propuesta->addMedia($archivo->getRealPath())
                ->usingFileName($archivo->getClientOriginalName())
                ->toMediaCollection('adjuntos');
        }

        if ($entidad->fresh()->estado?->nombre === 'borrado') {
            return redirect()->route("auditoria.{$prefijoRuta}.index")->with('ok', $eliminado);
        }

        return redirect()->route("auditoria.{$prefijoRuta}.show", $entidad)->with('ok', 'Propuesta de eliminación registrada.');
    }

    /**
     * Lo que necesita el modal de eliminación en el show(), o null si el usuario no
     * puede proponerla: si elige rol (D-052), si la aplica el comité en el acto
     * (D-053) y, para un plan, las tareas que caen con él.
     *
     * @return array{eligeRol: bool, comite: bool, tareas: list<string>}|null
     */
    protected function datosEliminacion(Model $entidad): ?array
    {
        $user = Auth::user();
        if (! $user->can('proponerEliminacion', $entidad)) {
            return null;
        }

        $eligeRol = $user->eligeRolEn($entidad->estado?->nombre, $entidad->area_id ?? $entidad->user?->area_id);

        return [
            'eligeRol' => $eligeRol,
            'comite' => ! $eligeRol && $user->esComite(),
            'tareas' => $entidad instanceof PlanAccion ? $entidad->tareasQueCaen()->pluck('nombre')->all() : [],
        ];
    }
}
