<div>
@if($abierto && $tarea)
@php
    $tareaVencida = $tarea->fecha && $tarea->fecha->lt(now()->startOfDay()) && $tarea->porcentaje_avance < 100;
@endphp
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
     wire:click.self="cerrar"
     @keydown.escape.window="$wire.cerrar()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4 shrink-0">
            <div class="flex-1 min-w-0">
                <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wide mb-0.5">Tarea</p>
                <h3 class="text-lg font-extrabold leading-tight {{ $tareaVencida ? 'text-red-700' : 'text-gray-900' }}">{{ $tarea->nombre }}</h3>
                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    <x-auditoria.estado-badge :estado="$tarea->estado" size="sm" />
                    @if($tareaVencida)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">Vencida</span>
                    @endif
                    @if($tarea->area)
                        <span class="text-[10px] text-gray-400 font-medium">{{ $tarea->area->nombre }}</span>
                    @endif
                </div>
                @if($tarea->descripcion)
                    <p class="text-xs text-gray-400 italic mt-1.5 line-clamp-2">{{ $tarea->descripcion }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <div class="mt-4 grid grid-cols-1 gap-2">
                    <div class="{{ $tarea->porcentaje_avance === 100 ? 'bg-green-50' : ($tareaVencida ? 'bg-red-50' : 'bg-amber-50') }} rounded-xl px-4 py-2 text-center">
                        <div class="text-xl font-extrabold {{ $tarea->porcentaje_avance === 100 ? 'text-green-700' : ($tareaVencida ? 'text-red-700' : 'text-amber-700') }}">
                            {{ $tarea->porcentaje_avance }}%
                        </div>
                        <div class="text-[9px] {{ $tarea->porcentaje_avance === 100 ? 'text-green-500' : ($tareaVencida ? 'text-red-500' : 'text-amber-500') }} uppercase font-bold tracking-wider mt-0.5">Avance</div>
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

                {{-- Columna izquierda: Info --}}
                <div class="col p-5 space-y-5">
                    <div>
                        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Información
                        </h4>

                        {{-- Barra de avance --}}
                        <div class="mb-4">
                            <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                <div class="h-2 rounded-full {{ $tarea->porcentaje_avance === 100 ? 'bg-green-500' : ($tareaVencida ? 'bg-red-400' : 'bg-amber-500') }}"
                                     style="width: {{ $tarea->porcentaje_avance }}%"></div>
                            </div>
                            <p class="text-center text-[10px] font-extrabold uppercase mt-1 {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : ($tareaVencida ? 'text-red-600' : 'text-amber-600') }}">
                                @if($tarea->porcentaje_avance === 100) Completada
                                @elseif($tareaVencida) Vencida
                                @else En Progreso
                                @endif
                            </p>
                        </div>

                        <dl class="space-y-3 text-sm">
                            @if($tarea->fecha)
                                <div class="flex justify-between">
                                    <dt class="text-gray-400 font-medium">Fecha límite</dt>
                                    <dd class="font-semibold {{ $tareaVencida ? 'text-red-700' : 'text-gray-800' }}">
                                        {{ $tarea->fecha->format('d/m/Y') }}
                                    </dd>
                                </div>
                            @endif
                            @if($tarea->user)
                                <div class="flex justify-between">
                                    <dt class="text-gray-400 font-medium">Asignado a</dt>
                                    <dd class="text-gray-700">{{ $tarea->user->name }}</dd>
                                </div>
                            @endif
                            @if($tarea->area)
                                <div class="flex justify-between">
                                    <dt class="text-gray-400 font-medium">Área</dt>
                                    <dd class="text-gray-700">{{ $tarea->area->nombre }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <dt class="text-gray-400 font-medium">Planes de acción</dt>
                                <dd class="text-gray-700">{{ $tarea->planesAccion->count() }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Columna derecha: Planes de Acción --}}
                <div class="col p-5">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Planes de Acción
                        <span class="ml-auto text-gray-300 font-normal">{{ $tarea->planesAccion->count() }}</span>
                    </h4>
                    @forelse($tarea->planesAccion as $plan)
                        <div class="mb-3 last:mb-0 p-2.5 rounded-xl border border-gray-100 bg-gray-50/40">
                            <div class="flex items-center gap-2 mb-1.5">
                                @if($plan->codigo)
                                    <span class="font-mono text-[9px] text-gray-400 uppercase shrink-0">{{ $plan->codigo }}</span>
                                @endif
                                <x-auditoria.estado-punto :estado="$plan->estado" soloPunto size="sm" />
                                <span class="flex-1 text-xs font-semibold text-gray-800 truncate">{{ $plan->nombre }}</span>
                            </div>
                            @if($plan->riesgos->count())
                                <div class="flex flex-wrap gap-1">
                                    @foreach($plan->riesgos as $riesgo)
                                        <span class="text-[9px] font-medium bg-orange-50 text-orange-700 border border-orange-100 px-1.5 py-0.5 rounded-full truncate max-w-[120px]">
                                            {{ $riesgo->nombre }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sin planes asociados.</p>
                    @endforelse
                </div>

            </div>
        </div>

        {{-- Footer --}}
        @can('view', $tarea)
        <div class="px-6 py-3 border-t border-gray-100 flex justify-end shrink-0 bg-gray-50/30">
            <a href="{{ route('auditoria.tareas.show', $tarea) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-teal-600 text-white text-sm font-bold rounded-xl hover:bg-teal-700 transition-colors">
                Ver tarea completa
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
