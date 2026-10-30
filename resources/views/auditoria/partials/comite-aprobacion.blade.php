{{--
    Comité de la aprobación vigente de un elemento aprobado (D-039), junto al badge
    de estado. Variables: $item (el modelo), $comite (Actualizacion::comiteVigenteDe(),
    calculado en el show() del controlador).
--}}
@if($item->estado?->nombre === 'aprobado')
    <span class="inline-flex items-center gap-1.5">
        @if($comite)
            <span class="font-semibold text-violet-700 bg-violet-50 rounded-md px-1.5 py-0.5">
                Comité{{ $comite['numero'] ? ' N° '.$comite['numero'] : '' }}{{ $comite['fecha'] ? ' · '.$comite['fecha']->format('d/m/Y') : '' }}
            </span>
        @else
            <span class="text-gray-400">Sin comité</span>
        @endif
        @can('registrarComite', [\App\Models\Auditoria\Actualizacion::class, $item])
            <x-auditoria.boton-cargar-comite :comite="$comite" />
        @endcan
    </span>
@endif
