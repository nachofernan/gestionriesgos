<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <h2 class="text-sm font-bold text-gray-700">Controles de Mitigación</h2>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">
                {{ count($seleccionados) }} control(es)
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
        @forelse ($seleccionados as $ctrl)
            <div wire:key="{{ $ctrl['id'] }}"
                 class="flex items-center gap-3 p-2.5 rounded-lg border transition-colors
                     {{ $editando ? 'border-blue-200 bg-blue-50/40' : 'border-gray-100 bg-gray-50/50 cursor-pointer hover:border-indigo-200 hover:bg-indigo-50/30' }}"
                 @if(!$editando) onclick="Livewire.dispatch('ver-control', {id: {{$ctrl['id']}}, mitigacion: {{$ctrl['mitigacion']}}})" @endif>
                <x-auditoria.estado-punto :color="$ctrl['estado_color']" :nombre="$ctrl['estado'] ?? 'borrador'" soloPunto />
                <div class="flex-1 min-w-0">
                    <span class="text-sm font-medium text-gray-700 truncate block">{{ $ctrl['nombre'] }}</span>
                </div>
                @if($editando)
                    <span class="text-[11px] text-gray-400 font-normal shrink-0">(mit. default: {{ $ctrl['mitigacion_default'] }})</span>
                @endif
                <div class="flex items-center gap-1.5 shrink-0">
                    <label class="text-[10px] text-gray-500 font-bold">Mit:</label>
                    @if($editando)
                        <input type="number"
                            value="{{ $ctrl['mitigacion'] }}"
                            wire:change="actualizarMitigacion({{ $ctrl['id'] }}, $event.target.value)"
                            min="0" max="10"
                            class="w-14 border-gray-200 rounded-lg px-2 py-1 text-xs text-center focus:border-blue-500 focus:ring-blue-500" />
                    @else
                        <span class="w-14 text-xs font-bold text-center text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-2 py-1">
                            {{ $ctrl['mitigacion'] }}
                        </span>
                    @endif
                </div>
                @if($editando)
                    <button type="button" wire:click="quitar({{ $ctrl['id'] }})"
                        class="text-gray-400 hover:text-red-500 transition-colors shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-1">Sin controles seleccionados.</p>
        @endforelse

        {{-- Acciones de edición --}}
        @if($editando)
            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="button" wire:click="abrirModal"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Agregar control
                </button>
                <button type="button" wire:click="guardar"
                    class="px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-lg hover:bg-blue-700 transition-colors">
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
                    <h3 class="text-sm font-bold text-gray-800">Buscar control</h3>
                    <button type="button" wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <input type="text" wire:model.live="busqueda" autofocus
                        placeholder="Escribí para buscar..."
                        class="w-full border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500" />
                    <div class="space-y-1.5 max-h-72 overflow-y-auto">
                        @forelse ($resultados as $ctrl)
                            <button type="button" wire:click="agregar({{ $ctrl->id }})"
                                class="w-full text-left px-4 py-3 rounded-lg hover:bg-blue-50 transition-colors border border-gray-100 hover:border-blue-200">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-gray-800 truncate">{{ $ctrl->nombre }}</span>
                                    <x-auditoria.estado-badge :estado="$ctrl->estado" size="xs" class="shrink-0" />
                                </div>
                                <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-500">
                                    <span class="font-bold text-blue-600 shrink-0">Mit. por defecto: {{ $ctrl->mitigacion_default }}</span>
                                    @if($ctrl->area)
                                        <span class="text-gray-300">·</span>
                                        <span class="truncate">{{ $ctrl->area->nombre }}</span>
                                    @endif
                                </div>
                            </button>
                        @empty
                            <p class="text-sm text-gray-400 italic px-4 py-3">
                                {{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar controles.' }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
