{{--
    Checks de una relación control-riesgo (D-040), para componentes Livewire.
    Panel en el flujo de la página (no flotante: los bloques tienen overflow
    hidden), pensado para ir debajo de la fila de la relación. Cada check dispara
    $accion (con %s en lugar de la clave). Recibe $checks, $tope y $deshabilitado.
    El número lo calcula el componente con Control::numeroRelacion().
--}}
@php
    $puntaje = \App\Models\Auditoria\Control::puntajeChecks($checks ?? []);
    $deshabilitado = $deshabilitado ?? false;
@endphp
<div class="mt-1.5 mb-1 rounded-xl border border-indigo-100 bg-white px-3 py-2">
    <div class="grid sm:grid-cols-2 gap-x-3 gap-y-0.5">
        @foreach(\App\Models\Auditoria\Control::CHECKS as $clave => $texto)
            <label class="flex items-center gap-2 rounded-lg px-1.5 py-1 text-xs text-gray-700 {{ $deshabilitado ? 'opacity-50' : 'hover:bg-gray-50 cursor-pointer' }}">
                <input type="checkbox" @checked(in_array($clave, $checks ?? [], true)) wire:click="{{ sprintf($accion, $clave) }}" @disabled($deshabilitado)
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="flex-1">{{ $texto }}</span>
                <span class="text-[10px] font-bold text-gray-400">+{{ \App\Models\Auditoria\Control::PUNTOS_POR_CHECK }}</span>
            </label>
        @endforeach
    </div>
    <p class="mt-1 border-t border-gray-100 px-1.5 pt-1.5 text-[11px] text-gray-500">
        Suma {{ $puntaje }} de {{ count(\App\Models\Auditoria\Control::CHECKS) * \App\Models\Auditoria\Control::PUNTOS_POR_CHECK }}.
        @if($puntaje > $tope) Mitiga {{ $tope }}: el control no puede mitigar más de lo que vale. @else El control puede mitigar hasta {{ $tope }}. @endif
    </p>
</div>
