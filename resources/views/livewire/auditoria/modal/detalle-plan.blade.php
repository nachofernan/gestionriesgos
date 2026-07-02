<div>
@if($abierto && $plan)
@php
    $avgAvance = $plan->tareas->count() ? (int) round($plan->tareas->avg('porcentaje_avance')) : null;
    $hoy = now()->startOfDay();
@endphp
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
     wire:click.self="cerrar"
     @keydown.escape.window="$wire.cerrar()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4 shrink-0">
            <div class="flex-1 min-w-0">
                @if($plan->codigo)
                    <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wide mb-0.5 font-mono">{{ $plan->codigo }}</p>
                @endif
                <h3 class="text-lg font-extrabold text-gray-900 leading-tight">{{ $plan->nombre }}</h3>
                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    <x-auditoria.estado-badge :estado="$plan->estado" size="sm" />
                    @if($plan->area)
                        <span class="text-[10px] text-gray-400 font-medium">{{ $plan->area->nombre }}</span>
                    @endif
                </div>
                @if($plan->descripcion)
                    <p class="text-xs text-gray-400 italic mt-1.5 line-clamp-2">{{ $plan->descripcion }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="bg-amber-50 rounded-xl px-4 py-2 text-center">
                        <div class="text-xl font-extrabold {{ $avgAvance === 100 ? 'text-green-700' : 'text-amber-700' }}">
                            {{ $avgAvance !== null ? $avgAvance . '%' : '—' }}
                        </div>
                        <div class="text-[9px] {{ $avgAvance === 100 ? 'text-green-500' : 'text-amber-500' }} uppercase font-bold tracking-wider mt-0.5">Avance</div>
                    </div>
                    <div class="bg-gray-50 rounded-xl px-4 py-2 text-center">
                        <div class="text-xl font-extrabold text-gray-700">{{ $plan->tareas->count() }}</div>
                        <div class="text-[9px] text-gray-400 uppercase font-bold tracking-wider mt-0.5">Tareas</div>
                    </div>
                </div>
                <button wire:click="cerrar" class="text-gray-400 hover:text-gray-600 transition-colors ml-1">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Cuerpo scrollable en 2 columnas --}}
        <div class="overflow-y-auto flex-1">
            <div class="grid grid-cols-2 divide-x divide-gray-100 min-h-full">

                {{-- Columna izquierda: Riesgos vinculados --}}
                <div class="col p-5">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Riesgos vinculados
                        <span class="ml-auto text-gray-300 font-normal">{{ $plan->riesgos->count() }}</span>
                    </h4>
                    @forelse($plan->riesgos as $riesgo)
                        <div class="py-1.5 border-b border-gray-50 last:border-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="flex-1 text-xs font-medium text-gray-700 truncate">{{ $riesgo->nombre }}</span>
                                <x-auditoria.estado-badge :estado="$riesgo->estado" size="xs" />
                            </div>
                            <div class="flex items-center gap-1.5">
                                @if($riesgo->tipoRiesgo)
                                    <span class="text-[9px] text-gray-400">{{ $riesgo->tipoRiesgo->nombre }}</span>
                                @endif
                                <span class="ml-auto text-[9px] font-bold bg-orange-50 text-orange-600 border border-orange-100 px-1.5 py-0.5 rounded-full shrink-0">
                                    T: {{ $riesgo->valor_total }}
                                </span>
                                <span class="text-[9px] font-bold bg-green-50 text-green-600 border border-green-100 px-1.5 py-0.5 rounded-full shrink-0">
                                    R: {{ $riesgo->valor_residual }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin riesgos vinculados.</p>
                    @endforelse
                </div>

                {{-- Columna derecha: Tareas --}}
                <div class="col p-5">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Tareas
                        <span class="ml-auto text-gray-300 font-normal">{{ $plan->tareas->count() }}</span>
                    </h4>
                    @forelse($plan->tareas as $tarea)
                        @php
                            $tareaVenc = $tarea->fecha && $tarea->fecha->lt($hoy) && $tarea->porcentaje_avance < 100;
                        @endphp
                        <div class="py-1.5 border-b border-gray-50 last:border-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-1 h-1 rounded-full shrink-0 {{ $tareaVenc ? 'bg-red-400' : 'bg-gray-300' }}"></span>
                                <span class="flex-1 text-xs truncate {{ $tareaVenc ? 'text-red-600 font-semibold' : 'text-gray-700 font-medium' }}">
                                    {{ $tarea->nombre }}
                                </span>
                                @if($tareaVenc)
                                    <span class="text-[8px] font-bold bg-red-100 text-red-600 px-1 py-0.5 rounded shrink-0">Vencida</span>
                                @endif
                                <span class="text-[10px] font-bold shrink-0 {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : ($tareaVenc ? 'text-red-600' : 'text-amber-600') }}">
                                    {{ $tarea->porcentaje_avance }}%
                                </span>
                            </div>
                            <div class="flex items-center gap-2 pl-3">
                                <div class="flex-1 bg-gray-200 rounded-full h-1 overflow-hidden">
                                    <div class="h-1 rounded-full {{ $tarea->porcentaje_avance === 100 ? 'bg-green-500' : ($tareaVenc ? 'bg-red-400' : 'bg-amber-500') }}"
                                         style="width: {{ $tarea->porcentaje_avance }}%"></div>
                                </div>
                                @if($tarea->fecha)
                                    <span class="text-[9px] text-gray-400 shrink-0">{{ $tarea->fecha->format('d/m/Y') }}</span>
                                @endif
                                @if($tarea->user)
                                    <span class="text-[9px] text-gray-400 shrink-0 truncate max-w-[80px]">{{ $tarea->user->name }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin tareas asociadas.</p>
                    @endforelse
                </div>

            </div>
        </div>

        {{-- Footer --}}
        @can('view', $plan)
        <div class="px-6 py-3 border-t border-gray-100 flex justify-end shrink-0 bg-gray-50/30">
            <a href="{{ route('auditoria.planes.show', $plan) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition-colors">
                Ver plan completo
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </a>
        </div>
        @endcan

    </div>
</div>
@endif
</div>
