<?php

namespace App\Http\Controllers\Auditoria\Concerns;

use App\Models\Auditoria\Area;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Área y Responsable de los forms de alta y edición de Control, Objetivo,
 * PlanAccion y Tarea. El área se elige sólo dentro de la línea del usuario
 * (User::idsAreasGestionables()) y es obligatoria: un elemento sin área lo
 * gestionaría cualquiera. El responsable sale de esa área, sus sub-áreas y sus
 * ancestros hasta su gerencia (Area::idsAreasDeResponsables()). En edición se conservan el área y
 * el responsable actuales aunque caigan fuera de la regla, para que abrir y
 * guardar el form no los pierda.
 * Tests: AltaAreaResponsableTest.
 */
trait OpcionesAreaResponsable
{
    private const MENSAJES_AREA_RESPONSABLE = [
        'area_id.required' => 'Debe elegir un área.',
        'area_id.in' => 'Sólo puede asignar su área o una de sus sub-áreas.',
        'user_id.in' => 'El responsable tiene que pertenecer al área elegida, a una de sus sub-áreas o a un área superior de su gerencia.',
    ];

    /**
     * Lo que consume el partial select-area-responsable: las áreas elegibles,
     * los usuarios candidatos y, por área, qué usuarios pueden ser responsables.
     */
    protected function opcionesAreaResponsable(?int $areaActual = null, ?int $responsableActual = null): array
    {
        $areas = Area::whereIn('id', $this->idsAreasPermitidas($areaActual))->orderBy('nombre')->get();
        $usuarios = User::whereNotNull('area_id')
            ->when($responsableActual, fn ($q) => $q->orWhere('id', $responsableActual))
            ->orderBy('name')
            ->get(['id', 'name', 'area_id']);

        $responsablesPorArea = [];
        foreach ($areas as $area) {
            $responsablesPorArea[$area->id] = $usuarios->whereIn('area_id', $area->idsAreasDeResponsables())
                ->pluck('id')->values()->all();
        }
        if ($areaActual && $responsableActual && ! in_array($responsableActual, $responsablesPorArea[$areaActual] ?? [])) {
            $responsablesPorArea[$areaActual][] = $responsableActual;
        }

        return compact('areas', 'usuarios', 'responsablesPorArea');
    }

    /** Reglas de area_id/user_id para store() (sin actuales) y update(). */
    protected function reglasAreaResponsable(Request $request, ?int $areaActual = null, ?int $responsableActual = null): array
    {
        $areaElegida = Area::find($request->input('area_id'));
        $responsables = $areaElegida
            ? User::whereIn('area_id', $areaElegida->idsAreasDeResponsables())->pluck('id')->all()
            : [];
        if ($responsableActual && $areaElegida && $areaElegida->id == $areaActual) {
            $responsables[] = $responsableActual;
        }

        return [
            'area_id' => ['required', 'exists:areas,id', Rule::in($this->idsAreasPermitidas($areaActual))],
            'user_id' => ['nullable', Rule::in($responsables)],
        ];
    }

    private function idsAreasPermitidas(?int $areaActual): array
    {
        return array_values(array_filter(array_unique(
            array_merge(Auth::user()->idsAreasGestionables(), [$areaActual])
        )));
    }
}
