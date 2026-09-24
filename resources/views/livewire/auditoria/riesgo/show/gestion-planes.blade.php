<div>
    @php
        $agregados = collect($diffEnCurso['agrega'] ?? [])->pluck('id');
        $cambiados = collect($diffEnCurso['cambia'] ?? [])->pluck('id');
        $quitados = $diffEnCurso['quita'] ?? [];
        $hoy = now()->startOfDay();
    @endphp

    <x-auditoria.bloque-riesgo ancla="planes" titulo="Planes de acción"
        subtitulo="Descuentan del residual cuando están aprobados y al 100%."
        :modo="$modo" :gerencias="$gerencias" :editando="$editando" :hayPropuestas="$propuestas->isNotEmpty()">

        <x-slot:accion>
            @if($puedeActualizar && !$editando)
                @include('livewire.auditoria.riesgo.show.partials.boton-editar', ['modo' => $modo])
            @endif
        </x-slot:accion>

        @forelse ($seleccionados as $plan)
            @php
                $planModel = $planesConTareas[$plan['id']] ?? null;
                $avance = $plan['avg_avance'];
                $completo = $avance === 100;
                $mitiga = $completo && ($plan['estado'] ?? null) === 'aprobado';
                $nuevo = $agregados->contains($plan['id']);
                $bloqueado = in_array($plan['id'], $bloqueados);
                // Sólo tareas aprobadas: las validadas/borrador son trabajo en curso que se ve en el plan.
                $tareas = $planModel?->tareas->filter(fn ($t) => $t->estado?->nombre === 'aprobado') ?? collect();
            @endphp
            <div wire:key="plan-{{ $plan['id'] }}"
                 class="rounded-xl border transition-colors {{ $nuevo ? 'border-dashed border-indigo-300 bg-indigo-50/50' : 'border-gray-100 bg-gray-50/60' }}">
                <div class="flex items-center gap-3 px-3 pt-2.5 pb-2">
                    <x-auditoria.estado-punto :color="$plan['estado_color']" :nombre="$plan['estado'] ?? 'borrador'" soloPunto />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 min-w-0">
                            @if($plan['codigo'] !== '—')
                                <span class="font-mono text-[10px] text-gray-400 uppercase shrink-0">{{ $plan['codigo'] }}</span>
                            @endif
                            @if($editando)
                                <span class="text-sm font-semibold text-gray-800 truncate">{{ $plan['nombre'] }}</span>
                            @else
                                <button type="button" onclick="Livewire.dispatch('ver-plan', {id: {{ $plan['id'] }}})"
                                    class="text-sm font-semibold text-gray-800 hover:text-indigo-700 truncate text-left">{{ $plan['nombre'] }}</button>
                            @endif
                        </div>
                        <p class="text-[11px] text-gray-500 truncate">
                            {{ collect([$plan['area'] ?? null, $plan['vencimiento'] ? 'vence '.$plan['vencimiento'] : null])->filter()->join(' · ') ?: '—' }}
                        </p>
                    </div>
                    @if($nuevo)
                        <span class="text-[10px] font-bold text-indigo-700">nuevo</span>
                    @elseif($cambiados->contains($plan['id']))
                        <span class="text-[10px] font-bold text-indigo-700">modificado</span>
                    @endif
                    @foreach($marcas[$plan['id']] ?? [] as $marca)
                        <span class="text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-2 py-0.5 whitespace-nowrap">{{ $marca }}</span>
                    @endforeach

                    @if($editando)
                        <label class="flex items-center gap-1 shrink-0 text-[10px] font-bold text-gray-500">
                            mit.
                            <input type="number" value="{{ $plan['mitigacion'] }}" min="0" max="20"
                                wire:change="actualizarMitigacion({{ $plan['id'] }}, $event.target.value)" @disabled($bloqueado)
                                class="w-14 border-gray-200 rounded-lg px-2 py-1 text-xs text-center focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400" />
                        </label>
                        <button type="button" wire:click="quitar({{ $plan['id'] }})" title="Quitar" @disabled($bloqueado)
                            class="text-gray-300 hover:text-red-500 transition-colors shrink-0 disabled:opacity-30 disabled:hover:text-gray-300 disabled:cursor-not-allowed">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @else
                        <span class="shrink-0 min-w-[3rem] text-center text-xs font-extrabold rounded-lg px-2 py-1 tabular-nums
                                     {{ $mitiga ? 'text-indigo-700 bg-indigo-50' : 'text-gray-400 bg-white border border-dashed border-gray-200' }}"
                              title="{{ $mitiga ? 'Descuenta del residual' : 'Descuenta recién cuando el plan esté aprobado y al 100%' }}">
                            −{{ $plan['mitigacion'] }}
                        </span>
                    @endif
                </div>

                {{-- Avance --}}
                <div class="px-3 pb-2.5 flex items-center gap-3">
                    <div class="flex-1 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full rounded-full {{ $completo ? 'bg-green-500' : 'bg-amber-400' }}" style="width: {{ $avance ?? 0 }}%"></div>
                    </div>
                    <span class="text-[11px] font-extrabold tabular-nums {{ $completo ? 'text-green-700' : 'text-amber-700' }}">{{ $avance !== null ? $avance.'%' : '—' }}</span>

                </div>

                {{-- <details> nativo y no Alpine: un x-data acá dejaría sin enganchar los wire:* de la fila. --}}
                @if(!$editando && $tareas->isNotEmpty())
                    <details class="group mx-3 mb-2.5">
                        <summary class="list-none cursor-pointer inline-flex items-center gap-1 text-[11px] font-semibold text-gray-500 hover:text-gray-800">
                            {{ $tareas->count() }} {{ $tareas->count() === 1 ? 'tarea aprobada' : 'tareas aprobadas' }}
                            <svg class="h-3 w-3 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <ul class="mt-2 pt-2 border-t border-gray-200/70 space-y-1">
                        @foreach($tareas as $tarea)
                            @php $vencida = $tarea->fecha && $tarea->fecha->lt($hoy) && $tarea->porcentaje_avance < 100; @endphp
                            <li class="flex items-center justify-between gap-2">
                                <button type="button" onclick="Livewire.dispatch('ver-tarea', {id: {{ $tarea->id }}})"
                                    class="text-xs text-left truncate hover:text-indigo-700 {{ $vencida ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ $tarea->nombre }}
                                    @if($vencida)<span class="ml-1 text-[9px] font-extrabold bg-red-100 text-red-700 px-1 py-0.5 rounded">Vencida</span>@endif
                                </button>
                                <span class="text-[11px] font-bold tabular-nums {{ $tarea->porcentaje_avance === 100 ? 'text-green-600' : ($vencida ? 'text-red-600' : 'text-amber-600') }}">{{ $tarea->porcentaje_avance }}%</span>
                            </li>
                        @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-2">Sin planes de acción asociados.</p>
        @endforelse

        @if($editando)
            @foreach($quitados as $item)
                <div wire:key="plan-quitado-{{ $item['id'] }}" class="flex items-center gap-3 px-3 py-2 rounded-xl border border-dashed border-gray-200">
                    <span class="flex-1 text-sm text-gray-400 line-through truncate">{{ $item['nombre'] }}</span>
                    <button type="button" wire:click="agregar({{ $item['id'] }})" class="text-[11px] font-bold text-gray-500 hover:text-indigo-700">Deshacer</button>
                </div>
            @endforeach
        @endif

        @if($error)
            <p class="text-xs text-red-600 font-medium">{{ $error }}</p>
        @endif

        <x-slot:pie>
            @if($editando)
                @include('livewire.auditoria.riesgo.show.partials.barra-edicion', [
                    'modo' => $modo, 'diff' => $diffEnCurso, 'agregarLabel' => 'Agregar plan',
                ])
            @endif
        </x-slot:pie>

        <x-slot:propuestas>
            @foreach($propuestas as $propuesta)
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="planesAccion" :estadoEntidad="$estadoModelo" />
            @endforeach
        </x-slot:propuestas>
    </x-auditoria.bloque-riesgo>

    @if($modalAbierto)
        <x-auditoria.modal-buscar titulo="Agregar plan de acción" placeholder="Nombre o código…">
            @forelse ($resultados as $plan)
                @php $avance = $plan->avance; @endphp
                <button type="button" wire:click="agregar({{ $plan->id }})"
                    class="w-full text-left px-4 py-3 rounded-xl hover:bg-indigo-50 transition-colors border border-gray-100 hover:border-indigo-200">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            @if($plan->codigo)<span class="font-mono text-[10px] text-gray-400 uppercase shrink-0">{{ $plan->codigo }}</span>@endif
                            <span class="font-semibold text-sm text-gray-800 truncate">{{ $plan->nombre }}</span>
                        </div>
                        <x-auditoria.estado-badge :estado="$plan->estado" size="xs" class="shrink-0" />
                    </div>
                    <p class="mt-0.5 text-[11px] text-gray-500 truncate">
                        <span class="font-bold {{ $avance === 100 ? 'text-green-600' : 'text-amber-600' }}">avance {{ $avance !== null ? $avance.'%' : '—' }}</span>
                        @if($plan->area) · {{ $plan->area->nombre }} @endif
                    </p>
                </button>
            @empty
                <p class="text-sm text-gray-400 italic px-4 py-3">{{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar planes.' }}</p>
            @endforelse
        </x-auditoria.modal-buscar>
    @endif
</div>
