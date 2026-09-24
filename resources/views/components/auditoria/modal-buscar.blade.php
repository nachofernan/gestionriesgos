{{--
    Modal de búsqueda para agregar un elemento a un bloque de riesgo/show. El
    slot trae los resultados; el input está atado a `busqueda` y el cierre a
    cerrarModal() del componente Livewire que lo incluye.
--}}
@props(['titulo', 'placeholder' => 'Escribí para buscar…'])
<div class="fixed inset-0 z-50 flex items-start sm:items-center justify-center bg-gray-900/40 backdrop-blur-[1px] p-4" wire:click.self="cerrarModal">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="px-5 pt-4 pb-3 flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-gray-900">{{ $titulo }}</h3>
            <button type="button" wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="px-5 pb-5 space-y-3">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
                <input type="text" wire:model.live.debounce.250ms="busqueda" autofocus placeholder="{{ $placeholder }}"
                    class="w-full border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <div class="relative">
                <div class="space-y-1.5 max-h-80 overflow-y-auto -mx-1 px-1">
                    {{ $slot }}
                </div>
                {{-- Mientras se agrega el elemento elegido: bloquea la lista y avisa. --}}
                <div wire:loading.flex wire:target="agregar"
                     class="absolute inset-0 items-center justify-center gap-2 rounded-xl bg-white/80 text-sm font-semibold text-indigo-700">
                    <x-auditoria.spinner /> Agregando…
                </div>
                <div wire:loading.flex wire:target="busqueda"
                     class="absolute right-2 -top-9 items-center text-gray-400">
                    <x-auditoria.spinner class="h-3.5 w-3.5" />
                </div>
            </div>
        </div>
    </div>
</div>
