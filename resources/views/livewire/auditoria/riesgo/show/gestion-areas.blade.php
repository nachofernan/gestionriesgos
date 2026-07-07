<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <h2 class="text-sm font-bold text-gray-700">Gerencias</h2>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">
                {{ count($seleccionados) }} gerencia(s)
            </span>
            @if(!$editando && $esBorrador)
                <button type="button" wire:click="activarEdicion"
                    class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-lg transition-colors">
                    Editar
                </button>
            @elseif(!$editando && !$esBorrador)
                <button type="button" wire:click="activarEdicion"
                    class="text-[10px] font-bold text-amber-600 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 px-2.5 py-1 rounded-lg transition-colors">
                    Proponer cambio
                </button>
            @endif
        </div>
    </div>

    <div class="p-5 space-y-3">

        {{-- Lista --}}
        @forelse ($seleccionados as $area)
            <div wire:key="{{ $area['id'] }}"
                 class="flex items-center gap-3 p-2.5 rounded-lg border transition-colors
                     {{ $editando ? 'border-teal-200 bg-teal-50/40' : 'border-gray-100 bg-gray-50/50' }}">
                <span class="flex-1 text-sm font-medium text-gray-700 min-w-0 truncate">{{ $area['nombre'] }}</span>
                @if($editando)
                    <button type="button" wire:click="quitar({{ $area['id'] }})"
                        class="text-gray-400 hover:text-red-500 transition-colors shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-1">Sin gerencias asociadas.</p>
        @endforelse

        @if($error)
            <p class="text-xs text-red-600 font-medium">{{ $error }}</p>
        @endif

        {{-- Acciones de edición --}}
        @if($editando)
            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="button" wire:click="abrirModal"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-teal-700 bg-teal-50 border border-teal-200 rounded-lg hover:bg-teal-100 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Agregar gerencia
                </button>
                <button type="button" wire:click="guardar"
                    class="px-4 py-2 bg-teal-600 text-white text-xs font-bold rounded-lg hover:bg-teal-700 transition-colors">
                    Guardar
                </button>
                <button type="button" wire:click="cancelarEdicion"
                    class="px-3 py-2 text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors">
                    Cancelar
                </button>
            </div>
        @endif

    </div>

    {{-- Modal de búsqueda (agregar) --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="cerrarModal">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800">Buscar gerencia</h3>
                    <button type="button" wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <input type="text" wire:model.live="busqueda" autofocus
                        placeholder="Escribí para buscar..."
                        class="w-full border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-teal-500 focus:ring-teal-500" />
                    <div class="space-y-1 max-h-64 overflow-y-auto">
                        @forelse ($resultados as $area)
                            <button type="button" wire:click="agregar({{ $area->id }})"
                                class="w-full flex items-center px-4 py-2.5 text-sm rounded-lg hover:bg-teal-50 transition-colors text-left border border-transparent hover:border-teal-200">
                                <span class="font-medium text-gray-700">{{ $area->nombre }}</span>
                            </button>
                        @empty
                            <p class="text-sm text-gray-400 italic px-4 py-3">
                                {{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar gerencias.' }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
