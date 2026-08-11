<div>
@if($abierto && $objetivo)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
     wire:click.self="cerrar"
     @keydown.escape.window="$wire.cerrar()">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh]">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4 shrink-0">
            <div class="flex-1 min-w-0">
                <p class="text-[11px] text-gray-400 font-medium uppercase tracking-wide mb-0.5">Objetivo</p>
                <h3 class="text-lg font-extrabold text-gray-900 leading-tight">{{ $objetivo->nombre }}</h3>
                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    <x-auditoria.estado-badge :estado="$objetivo->estado" size="sm" />
                    @if($objetivo->estrategico)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">Estratégico</span>
                    @endif
                    @if($objetivo->peis)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">PEIS</span>
                    @endif
                    @if($objetivo->area)
                        <span class="text-[10px] text-gray-400 font-medium">{{ $objetivo->area->nombre }}</span>
                    @endif
                </div>
                @if($objetivo->descripcion)
                    <p class="text-xs text-gray-400 italic mt-1.5 line-clamp-2">{{ $objetivo->descripcion }}</p>
                @endif
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <div class="mt-4 grid grid-cols-1 gap-2">
                    @if($objetivo->fecha_objetivo)
                    <div class="bg-purple-50 rounded-xl px-4 py-2 text-center">
                        <div class="text-base font-extrabold text-purple-700">{{ $objetivo->fecha_objetivo->format('d/m/Y') }}</div>
                        <div class="text-[9px] text-purple-500 uppercase font-bold tracking-wider mt-0.5">Fecha objetivo</div>
                    </div>
                    @else
                    <div class="bg-gray-50 rounded-xl px-4 py-2 text-center">
                        <div class="text-xl font-extrabold text-gray-500">{{ $objetivo->riesgos->count() }}</div>
                        <div class="text-[9px] text-gray-400 uppercase font-bold tracking-wider mt-0.5">Riesgos</div>
                    </div>
                    @endif
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
                        <dl class="space-y-3 text-sm">
                            @if($objetivo->fecha_objetivo)
                                <div class="flex justify-between">
                                    <dt class="text-gray-400 font-medium">Fecha objetivo</dt>
                                    <dd class="text-gray-700 font-semibold">{{ $objetivo->fecha_objetivo->format('d/m/Y') }}</dd>
                                </div>
                            @endif
                            @if($objetivo->area)
                                <div class="flex justify-between">
                                    <dt class="text-gray-400 font-medium">Área</dt>
                                    <dd class="text-gray-700">{{ $objetivo->area->nombre }}</dd>
                                </div>
                            @endif
                            @if($objetivo->user)
                                <div class="flex justify-between">
                                    <dt class="text-gray-400 font-medium">Responsable</dt>
                                    <dd class="text-gray-700">{{ $objetivo->user->name }}</dd>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <dt class="text-gray-400 font-medium">Riesgos asociados</dt>
                                <dd class="text-gray-700">{{ $objetivo->riesgos->count() }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Columna derecha: Riesgos asociados --}}
                <div class="col p-5">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Riesgos asociados
                        <span class="ml-auto text-gray-300 font-normal">{{ $objetivo->riesgos->count() }}</span>
                    </h4>
                    @forelse($objetivo->riesgos as $riesgo)
                        <div class="py-1.5 border-b border-gray-50 last:border-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <x-auditoria.estado-punto :estado="$riesgo->estado" soloPunto size="sm" />
                                <span class="flex-1 text-xs font-medium text-gray-700 truncate">{{ $riesgo->nombre }}</span>
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
                        <p class="text-xs text-gray-400 italic">Sin riesgos asociados.</p>
                    @endforelse
                </div>

            </div>
        </div>

        {{-- Footer --}}
        @can('view', $objetivo)
        <div class="px-6 py-3 border-t border-gray-100 flex justify-end shrink-0 bg-gray-50/30">
            <a href="{{ route('auditoria.objetivos.show', $objetivo) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-purple-600 text-white text-sm font-bold rounded-xl hover:bg-purple-700 transition-colors">
                Ver objetivo completo
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
