{{--
    "Cargar comité" / "Corregir comité" de una aprobación ya hecha (D-039). Deja una
    entrada nueva en el historial, no edita la aprobación. Dispara el evento global
    'registrar-comite' que atiende GestionActualizaciones (presente en todo show):
    con `aprobacionId` es esa aprobación; sin él, la aprobación vigente del elemento.
    No lleva wire:* adentro, así que su x-data no rompe los componentes que lo incluyen.
--}}
@props([
    'aprobacionId' => null,
    'comite' => null,
    'clase' => 'text-[11px] font-bold text-violet-700 hover:text-violet-900',
])
<div class="relative inline-block text-left" x-data="{ abierto: false, numero: @js($comite['numero'] ?? ''), fecha: @js(($comite['fecha'] ?? null)?->toDateString() ?? '') }"
     x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false">
    <button type="button" class="{{ $clase }}" x-on:click="abierto = !abierto">
        {{ $comite ? 'Corregir comité' : 'Cargar comité' }}
    </button>

    <div x-show="abierto" x-cloak x-transition.origin.top.left
         class="absolute left-0 z-40 mt-1 w-72 rounded-xl border border-gray-200 bg-white p-3 shadow-lg">
        <p class="text-xs text-gray-600 mb-2">Queda registrado en el historial; la aprobación no se modifica.</p>
        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">N° de comité</label>
        <input type="text" x-model="numero" maxlength="20"
               class="w-full border-gray-200 rounded-lg text-sm focus:border-violet-500 focus:ring-violet-500">
        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mt-2 mb-1">Fecha del comité</label>
        <input type="date" x-model="fecha" max="{{ now()->toDateString() }}"
               class="w-full border-gray-200 rounded-lg text-sm focus:border-violet-500 focus:ring-violet-500">
        <div class="mt-2 flex items-center justify-end gap-2">
            <button type="button" x-on:click="abierto = false" class="px-2 py-1 text-xs font-semibold text-gray-500 hover:text-gray-800">Cancelar</button>
            <button type="button" :disabled="!numero.trim() && !fecha"
                x-on:click="Livewire.dispatch('registrar-comite', { aprobacionId: @js($aprobacionId), comite: { numero: numero.trim(), fecha: fecha } }); abierto = false"
                class="px-3 py-1.5 text-xs font-bold rounded-lg text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed">Guardar</button>
        </div>
    </div>
</div>
