<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
        <h2 class="text-sm font-bold text-gray-700">Planes de Acción</h2>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wide">
                {{ count($seleccionados) }} plan(es)
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
        @forelse ($seleccionados as $plan)
            @php $planModel = $planesConTareas[$plan['id']] ?? null; @endphp
            <div wire:key="{{ $plan['id'] }}"
                 class="p-3 rounded-lg border {{ $editando ? 'border-indigo-200 bg-indigo-50/30' : 'border-gray-100 bg-gray-50/50' }}">
                <div class="flex items-center gap-2 mb-2">
                    @if($plan['codigo'] !== '—')
                        <span class="font-mono text-[10px] text-gray-400 uppercase shrink-0">{{ $plan['codigo'] }}</span>
                    @endif
                    <x-auditoria.estado-punto :color="$plan['estado_color']" :nombre="$plan['estado'] ?? 'borrador'" soloPunto />
                    @if(!$editando)
                        <button type="button"
                                onclick="Livewire.dispatch('ver-plan', {id: {{$plan['id']}}})"
                                class="flex-1 text-sm font-semibold text-indigo-700 hover:text-indigo-900 truncate text-left">
                            {{ $plan['nombre'] }}
                        </button>
                    @else
                        <span class="flex-1 text-sm font-semibold text-gray-700 truncate">{{ $plan['nombre'] }}</span>
                    @endif
                    @if($planModel && $planModel->tareas->count())
                        @php $avg = round($planModel->tareas->avg('porcentaje_avance')); @endphp
                        <span class="text-xs font-extrabold shrink-0 {{ $avg === 100 ? 'text-green-600' : 'text-amber-600' }}">{{ $avg }}%</span>
                    @endif
                    @php $completo = ($plan['avg_avance'] ?? null) === 100; @endphp
                    <div class="flex items-center gap-1.5 shrink-0" title="Mitigación que el plan aplica al valor residual cuando llega al 100%">
                        <label class="text-[10px] text-gray-500 font-bold">Mit:</label>
                        @if($editando)
                            <input type="number"
                                value="{{ $plan['mitigacion'] }}"
                                wire:change="actualizarMitigacion({{ $plan['id'] }}, $event.target.value)"
                                min="0" max="20"
                                class="w-14 border-gray-200 rounded-lg px-2 py-1 text-xs text-center focus:border-indigo-500 focus:ring-indigo-500" />
                        @else
                            <span class="w-14 text-xs font-bold text-center border rounded-lg px-2 py-1 {{ $completo ? 'text-green-700 bg-green-50 border-green-100' : 'text-gray-400 bg-gray-50 border-gray-200' }}">
                                {{ $plan['mitigacion'] }}
                            </span>
                        @endif
                    </div>
                    @if($editando)
                        <button type="button" wire:click="quitar({{ $plan['id'] }})"
                            class="text-gray-400 hover:text-red-500 transition-colors shrink-0">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    @endif
                </div>
                @if($editando)
                    <p class="text-[10px] text-gray-400 mb-1.5 -mt-1">La mitigación descuenta del valor residual sólo cuando el plan llega al 100%.</p>
                @endif
                @if($planModel && $planModel->tareas->count())
                    @php $hoyPlan = now()->startOfDay(); @endphp
                    <div class="space-y-1.5 pl-1 border-t border-gray-100 pt-2 mt-1">
                        @foreach($planModel->tareas as $tarea)
                            @php
                                $tareaVenc = $tarea->fecha && $tarea->fecha->lt($hoyPlan) && $tarea->porcentaje_avance < 100;
                            @endphp
                            <div class="flex items-center justify-between">
                                <button type="button"
                                        onclick="Livewire.dispatch('ver-tarea', {id: {{$tarea->id}}})"
                                        class="text-xs text-left hover:text-indigo-600 transition-colors truncate mr-2
                                            {{ $tareaVenc ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ $tarea->nombre }}
                                    @if($tareaVenc)
                                        <span class="text-[9px] font-extrabold bg-red-100 text-red-700 px-1 py-0.5 rounded ml-1">Vencida</span>
                                    @endif
                                </button>
                                <span class="text-xs font-bold shrink-0 {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : ($tareaVenc ? 'text-red-600' : 'text-amber-600') }}">
                                    {{ $tarea->porcentaje_avance }}%
                                </span>
                            </div>
                        @endforeach
                    </div>
                @elseif($planModel)
                    <p class="text-xs text-gray-400 pl-1 border-t border-gray-100 pt-2 mt-1">Sin tareas.</p>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-1">Sin planes de acción asociados.</p>
        @endforelse

        {{-- Acciones de edición --}}
        @if($editando)
            <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                <button type="button" wire:click="abrirModal"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Agregar plan
                </button>
                <button type="button" wire:click="guardar"
                    class="px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg hover:bg-indigo-700 transition-colors">
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
                    <h3 class="text-sm font-bold text-gray-800">Buscar plan de acción</h3>
                    <button type="button" wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <input type="text" wire:model.live="busqueda" autofocus
                        placeholder="Nombre o código..."
                        class="w-full border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <div class="space-y-1 max-h-64 overflow-y-auto">
                        @forelse ($resultados as $plan)
                            <button type="button" wire:click="agregar({{ $plan->id }})"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-sm rounded-lg hover:bg-indigo-50 transition-colors text-left border border-transparent hover:border-indigo-200">
                                @if($plan->codigo)
                                    <span class="font-mono text-[10px] text-gray-400 uppercase shrink-0">{{ $plan->codigo }}</span>
                                @endif
                                <span class="font-medium text-gray-700">{{ $plan->nombre }}</span>
                            </button>
                        @empty
                            <p class="text-sm text-gray-400 italic px-4 py-3">
                                {{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar planes.' }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
