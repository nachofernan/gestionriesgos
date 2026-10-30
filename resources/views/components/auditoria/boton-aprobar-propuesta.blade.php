{{--
    Botón "Aprobar" de una propuesta con un mini panel para el comité que la
    dictamina (D-039, opcional). Dispara el evento global 'resolver-actualizacion'
    que atiende GestionActualizaciones, igual que x-auditoria.boton-rechazar.
    No lleva wire:* adentro, así que su x-data no rompe los componentes que lo incluyen.
--}}
@props([
    'actualizacionId',
    'clase' => 'px-3 py-1.5 text-xs font-bold rounded-lg text-green-700 hover:bg-green-50',
    'aviso' => 'El cambio se aplica al registro.',
])
<div class="relative inline-block text-left" x-data="{ abierto: false, enviado: false, numero: '', fecha: '' }"
     x-on:click.outside="abierto = false" x-on:keydown.escape="abierto = false">
    <button type="button" class="{{ $clase }}" x-on:click="abierto = !abierto" :disabled="enviado"
            :class="enviado && 'opacity-50 cursor-wait'">Aprobar</button>

    <div x-show="abierto" x-cloak x-transition.origin.top.right
         class="absolute right-0 z-40 mt-1 w-72 rounded-xl border border-gray-200 bg-white p-3 shadow-lg text-left">
        <p class="text-xs text-gray-600 mb-2">{{ $aviso }}</p>
        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">N° de comité</label>
        <input type="text" x-model="numero" maxlength="20" placeholder="Opcional"
               class="w-full border-gray-200 rounded-lg text-sm focus:border-green-500 focus:ring-green-500">
        <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mt-2 mb-1">Fecha del comité</label>
        <input type="date" x-model="fecha" max="{{ now()->toDateString() }}"
               class="w-full border-gray-200 rounded-lg text-sm focus:border-green-500 focus:ring-green-500">
        <div class="mt-2 flex items-center justify-end gap-2">
            <button type="button" x-on:click="abierto = false" class="px-2 py-1 text-xs font-semibold text-gray-500 hover:text-gray-800">Cancelar</button>
            <button type="button"
                x-on:click="Livewire.dispatch('resolver-actualizacion', { id: {{ (int) $actualizacionId }}, accion: 'aprobar', comite: { numero: numero.trim(), fecha: fecha } }); abierto = false; enviado = true"
                class="px-3 py-1.5 text-xs font-bold rounded-lg text-white bg-green-600 hover:bg-green-700">Aprobar</button>
        </div>
    </div>
</div>
