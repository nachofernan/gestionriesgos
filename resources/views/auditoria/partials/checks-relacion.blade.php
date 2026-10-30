{{--
    Checks de una relación control-riesgo (D-040), para componentes Livewire.
    Botón con el número y un desplegable con los 5 checks; cada uno dispara
    $accion (con %s en lugar de la clave del check). Recibe $checks, $numero,
    $tope y $deshabilitado. El número lo calcula el componente con
    Control::numeroRelacion(): acá sólo se muestra.
--}}
@php
    $puntaje = \App\Models\Auditoria\Control::puntajeChecks($checks ?? []);
    $deshabilitado = $deshabilitado ?? false;
@endphp
<div class="relative shrink-0" x-data="{ abierto: false }" x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false">
    <button type="button" x-on:click="abierto = !abierto" @disabled($deshabilitado)
        class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-1 text-[11px] font-bold text-gray-700 hover:border-indigo-300 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
        mit. <span class="tabular-nums text-indigo-700">{{ $numero }}</span><span class="font-medium text-gray-400">/{{ $tope }}</span>
        <svg class="h-3 w-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="abierto" x-cloak x-transition.origin.top.right
        class="absolute right-0 z-30 mt-1 w-72 rounded-xl border border-gray-200 bg-white p-2 shadow-lg">
        @foreach(\App\Models\Auditoria\Control::CHECKS as $clave => $texto)
            <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs text-gray-700 hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" @checked(in_array($clave, $checks ?? [], true)) wire:click="{{ sprintf($accion, $clave) }}"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="flex-1">{{ $texto }}</span>
                <span class="text-[10px] font-bold text-gray-400">+{{ \App\Models\Auditoria\Control::PUNTOS_POR_CHECK }}</span>
            </label>
        @endforeach
        <p class="mt-1 border-t border-gray-100 px-2 pt-1.5 text-[11px] text-gray-500">
            Suma {{ $puntaje }}.
            @if($puntaje > $tope) Mitiga {{ $tope }}: el control no puede mitigar más de lo que vale. @endif
        </p>
    </div>
</div>
