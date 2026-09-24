<div>
    <x-auditoria.bloque-riesgo ancla="gerencias" titulo="Gerencias" :contador="count($seleccionados)" compacto
        :subtitulo="count($seleccionados) >= 2 ? 'Riesgo compartido: cada cambio necesita el voto de todas.' : 'Quién gestiona el riesgo.'"
        :modo="$modo" :gerencias="$gerencias" :editando="$editando" :hayPropuestas="$propuestas->isNotEmpty()">

        <x-slot:accion>
            @if(!$editando && $puedeGestionar)
                @include('livewire.auditoria.riesgo.show.partials.boton-editar', ['modo' => $modo])
            @endif
        </x-slot:accion>

        @if($editando)
            @foreach ($seleccionados as $area)
                <div wire:key="area-{{ $area['id'] }}" class="flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-100 bg-gray-50/60">
                    <span class="flex-1 text-sm font-semibold text-gray-800 truncate">{{ $area['nombre'] }}</span>
                    <button type="button" wire:click="quitar({{ $area['id'] }})" title="Quitar"
                        class="text-gray-300 hover:text-red-500 transition-colors shrink-0">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endforeach
        @else
            <div class="flex flex-wrap gap-1.5">
                @forelse ($seleccionados as $area)
                    <span wire:key="area-{{ $area['id'] }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-700 bg-gray-100 rounded-full pl-2 pr-2.5 py-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>{{ $area['nombre'] }}
                        @foreach($marcas[$area['id']] ?? [] as $marca)
                            <span class="text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-1.5">{{ $marca }}</span>
                        @endforeach
                    </span>
                @empty
                    <p class="text-sm text-gray-400 italic">Sin gerencias asociadas.</p>
                @endforelse
            </div>
        @endif

        @if($error)
            <p class="text-xs text-red-600 font-medium">{{ $error }}</p>
        @endif

        <x-slot:pie>
            @if($editando)
                @include('livewire.auditoria.riesgo.show.partials.barra-edicion', [
                    'modo' => $modo, 'diff' => null, 'agregarLabel' => 'Agregar',
                ])
            @endif
        </x-slot:pie>

        <x-slot:propuestas>
            @foreach($propuestas as $propuesta)
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="areas" :estadoEntidad="$estadoModelo" />
            @endforeach
        </x-slot:propuestas>
    </x-auditoria.bloque-riesgo>

    @if($modalAbierto)
        <x-auditoria.modal-buscar titulo="Agregar gerencia" placeholder="Nombre de la gerencia…">
            @forelse ($resultados as $area)
                <button type="button" wire:click="agregar({{ $area->id }})"
                    class="w-full text-left px-4 py-2.5 rounded-xl hover:bg-indigo-50 transition-colors border border-gray-100 hover:border-indigo-200">
                    <span class="font-semibold text-sm text-gray-800">{{ $area->nombre }}</span>
                </button>
            @empty
                <p class="text-sm text-gray-400 italic px-4 py-3">{{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar gerencias.' }}</p>
            @endforelse
        </x-auditoria.modal-buscar>
    @endif
</div>
