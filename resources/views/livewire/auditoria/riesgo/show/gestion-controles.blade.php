<div>
    @php
        $agregados = collect($diffEnCurso['agrega'] ?? [])->pluck('id');
        $cambiados = collect($diffEnCurso['cambia'] ?? [])->pluck('id');
        $quitados = $diffEnCurso['quita'] ?? [];
    @endphp

    <x-auditoria.bloque-riesgo ancla="controles" titulo="Controles de mitigación"
        subtitulo="Descuentan del residual sólo los controles aprobados."
        :modo="$modo" :gerencias="$gerencias" :editando="$editando" :hayPropuestas="$propuestas->isNotEmpty()">

        <x-slot:accion>
            @if($puedeActualizar && !$editando)
                @include('livewire.auditoria.riesgo.show.partials.boton-editar', ['modo' => $modo])
            @endif
        </x-slot:accion>

        @forelse ($seleccionados as $ctrl)
            @php
                $nuevo = $agregados->contains($ctrl['id']);
                $mitiga = ($ctrl['estado'] ?? null) === 'aprobado';
                $bloqueado = in_array($ctrl['id'], $bloqueados);
            @endphp
            <div wire:key="ctrl-{{ $ctrl['id'] }}"
                 class="flex items-center gap-3 px-3 py-2.5 rounded-xl border transition-colors
                     {{ $nuevo ? 'border-dashed border-indigo-300 bg-indigo-50/50' : 'border-gray-100 bg-gray-50/60' }}
                     {{ !$editando ? 'cursor-pointer hover:border-indigo-200 hover:bg-white' : '' }}"
                 @if(!$editando) onclick="Livewire.dispatch('ver-control', {id: {{ $ctrl['id'] }}, mitigacion: {{ $ctrl['mitigacion'] }}})" @endif>
                <x-auditoria.estado-punto :color="$ctrl['estado_color']" :nombre="$ctrl['estado'] ?? 'borrador'" soloPunto />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $ctrl['nombre'] }}</p>
                    <p class="text-[11px] text-gray-500 truncate">
                        {{ $ctrl['area'] ?? '—' }}
                        @if($editando) · default {{ $ctrl['mitigacion_default'] }} @endif
                    </p>
                </div>
                @if($nuevo)
                    <span class="text-[10px] font-bold text-indigo-700">nuevo</span>
                @elseif($cambiados->contains($ctrl['id']))
                    <span class="text-[10px] font-bold text-indigo-700">modificado</span>
                @endif
                @foreach($marcas[$ctrl['id']] ?? [] as $marca)
                    <span class="text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-2 py-0.5 whitespace-nowrap">{{ $marca }}</span>
                @endforeach

                @if($editando)
                    <label class="flex items-center gap-1 shrink-0 text-[10px] font-bold text-gray-500">
                        mit.
                        <input type="number" value="{{ $ctrl['mitigacion'] }}" min="0" max="10"
                            wire:change="actualizarMitigacion({{ $ctrl['id'] }}, $event.target.value)" @disabled($bloqueado)
                            class="w-14 border-gray-200 rounded-lg px-2 py-1 text-xs text-center focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400" />
                    </label>
                    <button type="button" wire:click="quitar({{ $ctrl['id'] }})" title="Quitar" @disabled($bloqueado)
                        class="text-gray-300 hover:text-red-500 transition-colors shrink-0 disabled:opacity-30 disabled:hover:text-gray-300 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @else
                    <span class="shrink-0 min-w-[3rem] text-center text-xs font-extrabold rounded-lg px-2 py-1 tabular-nums
                                 {{ $mitiga ? 'text-indigo-700 bg-indigo-50' : 'text-gray-400 bg-white border border-dashed border-gray-200' }}"
                          title="{{ $mitiga ? 'Descuenta del residual' : 'No descuenta hasta que el control esté aprobado' }}">
                        −{{ $ctrl['mitigacion'] }}
                    </span>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-2">Sin controles asociados.</p>
        @endforelse

        @if($editando)
            @foreach($quitados as $item)
                <div wire:key="ctrl-quitado-{{ $item['id'] }}" class="flex items-center gap-3 px-3 py-2 rounded-xl border border-dashed border-gray-200">
                    <span class="flex-1 text-sm text-gray-400 line-through truncate">{{ $item['nombre'] }}</span>
                    <button type="button" wire:click="agregar({{ $item['id'] }})" class="text-[11px] font-bold text-gray-500 hover:text-indigo-700">Deshacer</button>
                </div>
            @endforeach
        @endif

        <x-slot:pie>
            @if($editando)
                @include('livewire.auditoria.riesgo.show.partials.barra-edicion', [
                    'modo' => $modo, 'diff' => $diffEnCurso, 'agregarLabel' => 'Agregar control',
                ])
            @endif
        </x-slot:pie>

        <x-slot:propuestas>
            @foreach($propuestas as $propuesta)
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="controles" :estadoEntidad="$estadoModelo" />
            @endforeach
        </x-slot:propuestas>
    </x-auditoria.bloque-riesgo>

    @if($modalAbierto)
        <x-auditoria.modal-buscar titulo="Agregar control" placeholder="Nombre del control…">
            @forelse ($resultados as $ctrl)
                <button type="button" wire:click="agregar({{ $ctrl->id }})"
                    class="w-full text-left px-4 py-3 rounded-xl hover:bg-indigo-50 transition-colors border border-gray-100 hover:border-indigo-200">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-sm text-gray-800 truncate">{{ $ctrl->nombre }}</span>
                        <x-auditoria.estado-badge :estado="$ctrl->estado" size="xs" class="shrink-0" />
                    </div>
                    <p class="mt-0.5 text-[11px] text-gray-500 truncate">
                        <span class="font-bold text-indigo-700">mit. default {{ $ctrl->mitigacion_default }}</span>
                        @if($ctrl->area) · {{ $ctrl->area->nombre }} @endif
                    </p>
                </button>
            @empty
                <p class="text-sm text-gray-400 italic px-4 py-3">{{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar controles.' }}</p>
            @endforelse
        </x-auditoria.modal-buscar>
    @endif
</div>
