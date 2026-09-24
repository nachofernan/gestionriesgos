{{--
    Barra inferior de un bloque en edición: agregar, resumen en vivo de lo que se
    va a guardar (agrega / quita / cambia), cancelar y guardar. El botón principal
    dice "Guardar" o "Enviar propuesta" según `modo`. `diff` null = el bloque no
    calcula resumen (Gerencias); el guardado se habilita siempre.
    Variables: $modo, $diff (?array), $agregarLabel.
--}}
@php
    $conResumen = is_array($diff);
    $nAgrega = count($diff['agrega'] ?? []);
    $nQuita = count($diff['quita'] ?? []);
    $nCambia = count($diff['cambia'] ?? []);
    $hayCambios = !$conResumen || ($nAgrega + $nQuita + $nCambia) > 0;
    $esPropuesta = $modo !== 'directo';
@endphp
<div class="flex flex-wrap items-center gap-2">
    <button type="button" wire:click="abrirModal" wire:loading.attr="disabled" wire:target="abrirModal,guardar"
        class="disabled:opacity-50 inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-indigo-700 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        {{ $agregarLabel }}
    </button>

    @if($conResumen)
        <div class="flex items-center gap-1 text-[11px] font-bold">
            @if($nAgrega)<span class="px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700">+{{ $nAgrega }}</span>@endif
            @if($nQuita)<span class="px-1.5 py-0.5 rounded-md bg-rose-50 text-rose-700">−{{ $nQuita }}</span>@endif
            @if($nCambia)<span class="px-1.5 py-0.5 rounded-md bg-amber-50 text-amber-700">~{{ $nCambia }}</span>@endif
            @unless($hayCambios)<span class="font-medium text-gray-400">Sin cambios todavía</span>@endunless
        </div>
    @endif

    <div class="ml-auto flex items-center gap-2">
        <button type="button" wire:click="cancelarEdicion" wire:loading.attr="disabled" wire:target="guardar"
            class="disabled:opacity-40 px-3 py-2 text-xs font-semibold text-gray-500 hover:text-gray-800 transition-colors">
            Cancelar
        </button>
        <button type="button" wire:click="guardar" @disabled(!$hayCambios) wire:loading.attr="disabled" wire:target="guardar"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold rounded-lg text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed
                   {{ $esPropuesta ? 'bg-amber-600 hover:bg-amber-700' : 'bg-indigo-600 hover:bg-indigo-700' }}">
            <x-auditoria.spinner class="h-3.5 w-3.5" wire:loading wire:target="guardar" />
            {{ $esPropuesta ? 'Enviar propuesta' : 'Guardar' }}
        </button>
    </div>
</div>
