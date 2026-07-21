<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <h2 class="text-sm font-bold text-gray-700">Objetivos</h2>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">
                {{ count($seleccionados) }} objetivo(s)
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
        @forelse ($seleccionados as $obj)
            <div wire:key="{{ $obj['id'] }}"
                 class="flex items-center gap-3 p-2.5 rounded-lg border transition-colors
                     {{ $editando ? 'border-purple-200 bg-purple-50/40' : 'border-gray-100 bg-gray-50/50 cursor-pointer hover:border-purple-200 hover:bg-purple-50/30' }}"
                 @if(!$editando) onclick="Livewire.dispatch('ver-objetivo', {id: {{$obj['id']}}})" @endif>
                <x-auditoria.estado-punto :color="$obj['estado_color']" :nombre="$obj['estado'] ?? 'borrador'" soloPunto />
                <span class="flex-1 text-sm font-medium text-gray-700 min-w-0 truncate">{{ $obj['nombre'] }}</span>
                @if($editando)
                    <button type="button" wire:click="quitar({{ $obj['id'] }})"
                        class="text-gray-400 hover:text-red-500 transition-colors shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-1">Sin objetivos seleccionados.</p>
        @endforelse

        @if($error)
            <p class="text-xs text-red-600 font-medium">{{ $error }}</p>
        @endif

        {{-- Acciones de edición --}}
        @if($editando)
            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="button" wire:click="abrirModal"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-purple-700 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Agregar objetivo
                </button>
                <button type="button" wire:click="guardar"
                    class="px-4 py-2 bg-purple-600 text-white text-xs font-bold rounded-lg hover:bg-purple-700 transition-colors">
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
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800">Buscar objetivo</h3>
                    <button type="button" wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <input type="text" wire:model.live="busqueda" autofocus
                        placeholder="Escribí para buscar..."
                        class="w-full border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-purple-500 focus:ring-purple-500" />
                    <div class="space-y-1.5 max-h-72 overflow-y-auto">
                        @forelse ($resultados as $obj)
                            <button type="button" wire:click="agregar({{ $obj->id }})"
                                class="w-full text-left px-4 py-3 rounded-lg hover:bg-purple-50 transition-colors border border-gray-100 hover:border-purple-200">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-gray-800 truncate">{{ $obj->nombre }}</span>
                                    <x-auditoria.estado-badge :estado="$obj->estado" size="xs" class="shrink-0" />
                                </div>
                                <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-500">
                                    @if($obj->fecha_objetivo)
                                        <span class="shrink-0">Fecha objetivo: {{ $obj->fecha_objetivo->format('d/m/Y') }}</span>
                                    @endif
                                    @if($obj->area)
                                        @if($obj->fecha_objetivo)<span class="text-gray-300">·</span>@endif
                                        <span class="truncate">{{ $obj->area->nombre }}</span>
                                    @endif
                                </div>
                            </button>
                        @empty
                            <p class="text-sm text-gray-400 italic px-4 py-3">
                                {{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar objetivos.' }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
