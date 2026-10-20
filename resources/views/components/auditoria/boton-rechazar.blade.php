{{--
    Botón "Rechazar" que pide el motivo (obligatorio en todo rechazo, D-031) en un
    mini panel. Dos modos:
    - `url`: rechazo de un elemento, POST con el campo `motivo` (los *Controller::rechazar()).
    - `actualizacionId`: rechazo de una propuesta, vía el evento global
      'resolver-actualizacion' que atiende GestionActualizaciones.
    No lleva wire:* adentro, así que su x-data no rompe los componentes que lo incluyen.
--}}
@props([
    'url' => null,
    'actualizacionId' => null,
    'clase' => 'px-3 py-1.5 text-xs font-bold rounded-lg text-red-600 hover:bg-red-50',
    'etiqueta' => 'Rechazar',
])
<div class="relative inline-block text-left" x-data="{ abierto: false, motivo: '' }" x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false">
    <button type="button" class="{{ $clase }}" x-on:click="abierto = !abierto">{{ $etiqueta }}</button>

    <div x-show="abierto" x-cloak x-transition.origin.top.right
         class="absolute right-0 z-40 mt-1 w-72 rounded-xl border border-gray-200 bg-white p-3 shadow-lg">
        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Motivo del rechazo *</label>
        @if($url)
            <form method="POST" action="{{ $url }}" x-on:submit="if (motivo.trim().length < 3) $event.preventDefault()">
                @csrf
                <textarea name="motivo" x-model="motivo" rows="3" required minlength="3" placeholder="Queda registrado en el historial"
                    class="w-full border-gray-200 rounded-lg text-sm focus:border-red-400 focus:ring-red-400"></textarea>
                <div class="mt-2 flex items-center justify-end gap-2">
                    <button type="button" x-on:click="abierto = false" class="px-2 py-1 text-xs font-semibold text-gray-500 hover:text-gray-800">Cancelar</button>
                    <button type="submit" :disabled="motivo.trim().length < 3"
                        class="px-3 py-1.5 text-xs font-bold rounded-lg text-white bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed">Rechazar</button>
                </div>
            </form>
        @else
            <textarea x-model="motivo" rows="3" placeholder="Queda registrado en el historial"
                class="w-full border-gray-200 rounded-lg text-sm focus:border-red-400 focus:ring-red-400"></textarea>
            <div class="mt-2 flex items-center justify-end gap-2">
                <button type="button" x-on:click="abierto = false" class="px-2 py-1 text-xs font-semibold text-gray-500 hover:text-gray-800">Cancelar</button>
                <button type="button" :disabled="motivo.trim().length < 3"
                    x-on:click="Livewire.dispatch('resolver-actualizacion', { id: {{ (int) $actualizacionId }}, accion: 'rechazar', motivo: motivo.trim() }); abierto = false; motivo = ''"
                    class="px-3 py-1.5 text-xs font-bold rounded-lg text-white bg-red-600 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed">Rechazar</button>
            </div>
        @endif
    </div>
</div>
