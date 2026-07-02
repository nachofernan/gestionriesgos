<div>
@if($abierto && $riesgo)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
     wire:click.self="cerrar"
     @keydown.escape.window="$wire.cerrar()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4 shrink-0">
            <div class="flex-1 min-w-0">
                @if($riesgo->tipoRiesgo)
                    <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wide mb-0.5">{{ $riesgo->tipoRiesgo->nombre }}</p>
                @endif
                <h3 class="text-lg font-extrabold text-gray-900 leading-tight">{{ $riesgo->nombre }}</h3>
                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    <x-auditoria.estado-badge :estado="$riesgo->estado" size="sm" />
                    @if($riesgo->area)
                        <span class="text-[10px] text-gray-400 font-medium">{{ $riesgo->area->nombre }}</span>
                    @endif
                </div>
                @if($riesgo->descripcion)
                    <p class="text-xs text-gray-400 italic mt-1.5 line-clamp-2">{{ $riesgo->descripcion }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="col-span-1 bg-orange-50 rounded-xl px-4 py-2 text-center">
                        <div class="text-xl font-extrabold text-orange-700">{{ $riesgo->valor_total }}</div>
                        <div class="text-[9px] text-orange-500 uppercase font-bold tracking-wider mt-0.5">Total</div>
                    </div>
                    <div class="col-span-1 bg-green-50 rounded-xl px-4 py-2 text-center">
                        <div class="text-xl font-extrabold text-green-700">{{ $riesgo->valor_residual }}</div>
                        <div class="text-[9px] text-green-500 uppercase font-bold tracking-wider mt-0.5">Residual</div>
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

                {{-- Columna izquierda: Controles + Objetivos --}}
                <div class="col p-5 space-y-5">

                    {{-- Controles --}}
                    <div>
                        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            Controles
                            <span class="ml-auto text-gray-300 font-normal">{{ $riesgo->controles->count() }}</span>
                        </h4>
                        @forelse($riesgo->controles as $ctrl)
                            <div class="flex items-center gap-2 py-1.5 border-b border-gray-50 last:border-0">
                                <div class="flex-1 min-w-0">
                                    <span class="text-xs font-medium text-gray-700 block truncate">{{ $ctrl->nombre }}</span>
                                </div>
                                <x-auditoria.estado-badge :estado="$ctrl->estado" size="xs" />
                                <span class="text-[9px] font-bold bg-blue-50 text-blue-600 border border-blue-100 px-1.5 py-0.5 rounded-full shrink-0">
                                    Mit: {{ $ctrl->pivot->mitigacion ?? $ctrl->mitigacion_default }}
                                </span>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic">Sin controles asociados.</p>
                        @endforelse
                    </div>

                    {{-- Objetivos --}}
                    <div>
                        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Objetivos
                            <span class="ml-auto text-gray-300 font-normal">{{ $riesgo->objetivos->count() }}</span>
                        </h4>
                        @forelse($riesgo->objetivos as $obj)
                            <div class="flex items-center gap-2 py-1.5 border-b border-gray-50 last:border-0">
                                <span class="flex-1 text-xs font-medium text-gray-700 truncate">{{ $obj->nombre }}</span>
                                <x-auditoria.estado-badge :estado="$obj->estado" size="xs" />
                                @if($obj->estrategico ?? false)
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-600 shrink-0">E</span>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic">Sin objetivos asociados.</p>
                        @endforelse
                    </div>

                </div>

                {{-- Columna derecha: Planes de Acción --}}
                <div class="col p-5">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Planes de Acción
                        <span class="ml-auto text-gray-300 font-normal">{{ $riesgo->planesAccion->count() }}</span>
                    </h4>
                    @forelse($riesgo->planesAccion as $plan)
                        @php
                            $avgAvance = $plan->tareas->count() ? (int) round($plan->tareas->avg('porcentaje_avance')) : null;
                            $hoy = now()->startOfDay();
                        @endphp
                        <div class="mb-3 last:mb-0 p-2.5 rounded-xl border border-gray-100 bg-gray-50/40">
                            <div class="flex items-center gap-2 mb-1.5">
                                @if($plan->codigo)
                                    <span class="font-mono text-[9px] text-gray-400 uppercase shrink-0">{{ $plan->codigo }}</span>
                                @endif
                                <span class="flex-1 text-sm font-semibold text-gray-800 truncate">{{ $plan->nombre }}</span>
                                <x-auditoria.estado-badge :estado="$plan->estado" size="xs" />
                            </div>
                            @if($avgAvance !== null)
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-1 overflow-hidden">
                                        <div class="h-1 rounded-full {{ $avgAvance === 100 ? 'bg-green-500' : 'bg-amber-500' }}"
                                             style="width: {{ $avgAvance }}%"></div>
                                    </div>
                                    <span class="text-[9px] font-bold {{ $avgAvance === 100 ? 'text-green-600' : 'text-amber-600' }} shrink-0">{{ $avgAvance }}%</span>
                                </div>
                            @endif
                            @if($plan->tareas->count())
                                <div class="space-y-1 pl-1 border-t border-gray-100 pt-1.5">
                                    @foreach($plan->tareas as $tarea)
                                        @php
                                            $tareaVenc = $tarea->fecha && $tarea->fecha->lt($hoy) && $tarea->porcentaje_avance < 100;
                                        @endphp
                                        <div class="flex items-center gap-2">
                                            <span class="w-1 h-1 rounded-full shrink-0 {{ $tareaVenc ? 'bg-red-400' : 'bg-gray-300' }}"></span>
                                            <span class="flex-1 text-xs truncate {{ $tareaVenc ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                                {{ $tarea->nombre }}
                                            </span>
                                            @if($tareaVenc)
                                                <span class="text-[8px] font-bold bg-red-100 text-red-600 px-1 py-0.5 rounded shrink-0">Vencida</span>
                                            @endif
                                            <span class="text-[10px] font-bold shrink-0 {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : ($tareaVenc ? 'text-red-600' : 'text-amber-600') }}">
                                                {{ $tarea->porcentaje_avance }}%
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-400 italic pl-1 border-t border-gray-100 pt-1.5">Sin tareas.</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin planes de acción asociados.</p>
                    @endforelse
                </div>

            </div>
        </div>

        {{-- Footer --}}
        @can('view', $riesgo)
        <div class="px-6 py-3 border-t border-gray-100 flex justify-end shrink-0 bg-gray-50/30">
            <a href="{{ route('auditoria.riesgos.show', $riesgo) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-600 text-white text-sm font-bold rounded-xl hover:bg-orange-700 transition-colors">
                Ver riesgo completo
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
