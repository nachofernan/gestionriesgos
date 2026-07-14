<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <h2 class="text-sm font-bold text-gray-700">Tareas del Plan</h2>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">
                {{ count($seleccionados) }} tarea(s)
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

    <div class="p-5 space-y-2">

        {{-- Lista --}}
        @php $hoy = now()->startOfDay(); @endphp
        @forelse ($seleccionados as $tarea)
            @php
                $vencida = $tarea['fecha']
                    && \Carbon\Carbon::parse($tarea['fecha'])->lt($hoy)
                    && $tarea['porcentaje_avance'] < 100;
            @endphp
            <div wire:key="{{ $tarea['id'] }}"
                 class="flex items-center gap-3 p-2.5 rounded-lg border transition-colors
                     @if($editando) border-teal-200 bg-teal-50/40
                     @elseif($vencida) border-red-200 bg-red-50/30
                     @else border-gray-100 bg-gray-50/50 cursor-pointer hover:border-teal-200 hover:bg-teal-50/20
                     @endif"
                 @if(!$editando) onclick="Livewire.dispatch('ver-tarea', {id: {{$tarea['id']}}})" @endif>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <x-auditoria.estado-punto :color="$tarea['estado_color']" :nombre="$tarea['estado'] ?? 'borrador'" soloPunto />
                            <span class="text-sm font-medium truncate
                                {{ $vencida ? 'text-red-700 font-semibold' : 'text-gray-700' }}">
                                {{ $tarea['nombre'] }}
                            </span>
                            @if($vencida)
                                <span class="text-[9px] font-extrabold bg-red-100 text-red-700 px-1.5 py-0.5 rounded uppercase tracking-wide shrink-0">Vencida</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($tarea['fecha'])
                                <span class="text-[10px] {{ $vencida ? 'text-red-500 font-semibold' : 'text-gray-400' }}">
                                    {{ \Carbon\Carbon::parse($tarea['fecha'])->format('d/m/Y') }}
                                </span>
                            @endif
                            <div class="w-16 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                <div class="h-1.5 rounded-full
                                    {{ $tarea['porcentaje_avance'] === 100 ? 'bg-green-500' : ($vencida ? 'bg-red-400' : 'bg-amber-500') }}"
                                     style="width: {{ $tarea['porcentaje_avance'] }}%"></div>
                            </div>
                            <span class="text-[10px] font-bold
                                {{ $tarea['porcentaje_avance'] === 100 ? 'text-green-600' : ($vencida ? 'text-red-600' : 'text-amber-600') }}">
                                {{ $tarea['porcentaje_avance'] }}%
                            </span>
                        </div>
                    </div>
                </div>
                @if($editando)
                    <button type="button" wire:click="quitar({{ $tarea['id'] }})"
                        class="text-gray-400 hover:text-red-500 transition-colors shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-1">Sin tareas asociadas.</p>
        @endforelse

        {{-- Formulario nueva tarea inline --}}
        @if($creandoTarea)
            <div class="mt-3 p-4 rounded-xl border border-teal-200 bg-teal-50/30 space-y-3">
                <p class="text-xs font-bold text-teal-700 uppercase tracking-wide">Nueva tarea</p>
                <div>
                    <input type="text" wire:model="nuevaNombre" placeholder="Nombre de la tarea *"
                        class="w-full border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500" />
                    @error('nuevaNombre') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] text-gray-500 font-semibold mb-1 uppercase">Fecha límite</label>
                        <input type="date" wire:model="nuevaFecha"
                            class="w-full border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-teal-500 focus:ring-teal-500" />
                    </div>
                    <div x-data="{ avance: @entangle('nuevaPorcentaje') }">
                        <label class="block text-[10px] text-gray-500 font-semibold mb-1 uppercase">Avance (<span x-text="avance + '%'"></span>)</label>
                        <input type="range" wire:model="nuevaPorcentaje" min="0" max="100" step="5"
                            class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-teal-600" />
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" wire:click="guardarNuevaTarea"
                        class="px-4 py-2 bg-teal-600 text-white text-xs font-bold rounded-lg hover:bg-teal-700 transition-colors">
                        Crear y agregar
                    </button>
                    <button type="button" wire:click="cerrarFormNuevaTarea"
                        class="px-3 py-2 text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors">
                        Cancelar
                    </button>
                </div>
            </div>
        @endif

        {{-- Acciones de edición --}}
        @if($editando)
            <div class="flex items-center gap-3 pt-3 border-t border-gray-100">
                <button type="button" wire:click="abrirModal"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-teal-700 bg-teal-50 border border-teal-200 rounded-lg hover:bg-teal-100 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Agregar existente
                </button>
                @if(!$creandoTarea)
                    <button type="button" wire:click="abrirFormNuevaTarea"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nueva tarea
                    </button>
                @endif
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

    {{-- Modal de búsqueda (agregar existente) --}}
    @if($modalAbierto)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" wire:click.self="cerrarModal">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800">Buscar tarea</h3>
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
                        @forelse ($resultados as $tarea)
                            @php $tarVencida = $tarea->fecha && $tarea->fecha->lt($hoy) && $tarea->porcentaje_avance < 100; @endphp
                            <button type="button" wire:click="agregar({{ $tarea->id }})"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg hover:bg-teal-50 transition-colors text-left border border-transparent hover:border-teal-200">
                                <div class="flex items-center gap-2 min-w-0 mr-3">
                                    <span class="font-medium text-gray-700 truncate">{{ $tarea->nombre }}</span>
                                    @if($tarVencida)
                                        <span class="text-[9px] font-bold bg-red-100 text-red-700 px-1.5 py-0.5 rounded uppercase shrink-0">Vencida</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($tarea->fecha)
                                        <span class="text-[10px] {{ $tarVencida ? 'text-red-500' : 'text-gray-400' }}">
                                            {{ $tarea->fecha->format('d/m/Y') }}
                                        </span>
                                    @endif
                                    <div class="w-12 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                        <div class="h-1.5 rounded-full {{ $tarea->porcentaje_avance === 100 ? 'bg-green-500' : 'bg-amber-500' }}"
                                             style="width: {{ $tarea->porcentaje_avance }}%"></div>
                                    </div>
                                    <span class="text-[10px] font-bold {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : 'text-amber-600' }}">
                                        {{ $tarea->porcentaje_avance }}%
                                    </span>
                                </div>
                            </button>
                        @empty
                            <p class="text-sm text-gray-400 italic px-4 py-3">
                                {{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar tareas.' }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
