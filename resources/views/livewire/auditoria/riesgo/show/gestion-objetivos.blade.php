<div>
    @php
        $agregados = collect($diffEnCurso['agrega'] ?? [])->pluck('id');
        $quitados = $diffEnCurso['quita'] ?? [];
    @endphp

    <x-auditoria.bloque-riesgo ancla="objetivos" titulo="Objetivos"
        subtitulo="A qué objetivos estratégicos amenaza este riesgo."
        :modo="$modo" :gerencias="$gerencias" :editando="$editando" :hayPropuestas="$propuestas->isNotEmpty()">

        <x-slot:accion>
            @if($puedeActualizar && !$editando)
                @include('livewire.auditoria.riesgo.show.partials.boton-editar', ['modo' => $modo])
            @endif
        </x-slot:accion>

        @forelse ($seleccionados as $obj)
            @php
                $nuevo = $agregados->contains($obj['id']);
                $bloqueado = in_array($obj['id'], $bloqueados);
            @endphp
            <div wire:key="obj-{{ $obj['id'] }}"
                 class="group flex items-center gap-3 px-3 py-2.5 rounded-xl border transition-colors
                     {{ $nuevo ? 'border-dashed border-indigo-300 bg-indigo-50/50' : 'border-gray-100 bg-gray-50/60' }}
                     {{ !$editando ? 'cursor-pointer hover:border-indigo-200 hover:bg-white' : '' }}"
                 @if(!$editando) onclick="Livewire.dispatch('ver-objetivo', {id: {{ $obj['id'] }}})" @endif>
                <x-auditoria.estado-punto :color="$obj['estado_color']" :nombre="$obj['estado'] ?? 'borrador'" soloPunto />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $obj['nombre'] }}</p>
                    <p class="text-[11px] text-gray-500 truncate">
                        {{ collect([$obj['area'] ?? null, isset($obj['fecha_objetivo']) ? 'meta '.$obj['fecha_objetivo'] : null])->filter()->join(' · ') ?: '—' }}
                    </p>
                </div>
                @if(!empty($obj['estrategico']))
                    <span class="hidden sm:inline text-[10px] font-bold text-indigo-700 bg-indigo-50 rounded-md px-1.5 py-0.5">Estratégico</span>
                @endif
                @if(!empty($obj['peis']))
                    <span class="hidden sm:inline text-[10px] font-bold text-violet-700 bg-violet-50 rounded-md px-1.5 py-0.5">PEIS</span>
                @endif
                @if($nuevo)
                    <span class="text-[10px] font-bold text-indigo-700">nuevo</span>
                @endif
                @foreach($marcas[$obj['id']] ?? [] as $marca)
                    <span class="text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-2 py-0.5 whitespace-nowrap">{{ $marca }}</span>
                @endforeach
                @if($editando)
                    <button type="button" wire:click="quitar({{ $obj['id'] }})" title="Quitar" @disabled($bloqueado)
                        class="text-gray-300 hover:text-red-500 transition-colors shrink-0 disabled:opacity-30 disabled:hover:text-gray-300 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-2">Sin objetivos asociados.</p>
        @endforelse

        @if($editando)
            @foreach($quitados as $item)
                <div wire:key="obj-quitado-{{ $item['id'] }}" class="flex items-center gap-3 px-3 py-2 rounded-xl border border-dashed border-gray-200">
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
                    'modo' => $modo, 'diff' => $diffEnCurso, 'agregarLabel' => 'Agregar objetivo',
                ])
            @endif
        </x-slot:pie>

        <x-slot:propuestas>
            @foreach($propuestas as $propuesta)
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="objetivos" :estadoEntidad="$estadoModelo" />
            @endforeach
        </x-slot:propuestas>
    </x-auditoria.bloque-riesgo>

    @if($modalAbierto)
        <x-auditoria.modal-buscar titulo="Agregar objetivo" placeholder="Nombre del objetivo…">
            @forelse ($resultados as $obj)
                <button type="button" wire:click="agregar({{ $obj->id }})"
                    class="w-full text-left px-4 py-3 rounded-xl hover:bg-indigo-50 transition-colors border border-gray-100 hover:border-indigo-200">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-sm text-gray-800 truncate">{{ $obj->nombre }}</span>
                        <x-auditoria.estado-badge :estado="$obj->estado" size="xs" class="shrink-0" />
                    </div>
                    <p class="mt-0.5 text-[11px] text-gray-500 truncate">
                        {{ collect([$obj->area?->nombre, $obj->fecha_objetivo ? 'meta '.$obj->fecha_objetivo->format('d/m/Y') : null])->filter()->join(' · ') }}
                    </p>
                </button>
            @empty
                <p class="text-sm text-gray-400 italic px-4 py-3">{{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar objetivos.' }}</p>
            @endforelse
        </x-auditoria.modal-buscar>
    @endif
</div>
