<div>
    @php
        $hoy = now()->startOfDay();
        $agregados = collect($diffEnCurso['agrega'] ?? [])->pluck('id');
        $quitados = $diffEnCurso['quita'] ?? [];
        $input = 'w-full border-gray-200 rounded-lg text-sm focus:border-indigo-500 focus:ring-indigo-500';
        $label = 'block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1';
    @endphp

    <x-auditoria.bloque-riesgo ancla="tareas" titulo="Tareas" :contador="count($seleccionados)" sujeto="plan"
        subtitulo="El avance del plan es el promedio de sus tareas aprobadas."
        :modo="$modo" :editando="$editando" :hayPropuestas="$propuestas->isNotEmpty()">

        <x-slot:accion>
            @if($puedeActualizar && !$editando)
                @include('livewire.auditoria.riesgo.show.partials.boton-editar', ['modo' => $modo])
            @endif
        </x-slot:accion>

        @forelse ($seleccionados as $tarea)
            @php
                $nueva = $agregados->contains($tarea['id']);
                $bloqueada = in_array($tarea['id'], $bloqueados);
                $avance = (int) $tarea['porcentaje_avance'];
                $vencida = $tarea['fecha'] && \Carbon\Carbon::parse($tarea['fecha'])->lt($hoy) && $avance < 100;
                $cuenta = ($tarea['estado'] ?? null) === 'aprobado';
            @endphp
            <div wire:key="tarea-{{ $tarea['id'] }}"
                 class="flex items-center gap-3 px-3 py-2.5 rounded-xl border transition-colors
                     {{ $nueva ? 'border-dashed border-indigo-300 bg-indigo-50/50' : ($vencida ? 'border-red-100 bg-red-50/40' : 'border-gray-100 bg-gray-50/60') }}
                     {{ !$editando ? 'cursor-pointer hover:border-indigo-200 hover:bg-white' : '' }}"
                 @if(!$editando) onclick="Livewire.dispatch('ver-tarea', {id: {{ $tarea['id'] }}})" @endif>
                <x-auditoria.estado-punto :color="$tarea['estado_color']" :nombre="$tarea['estado'] ?? 'borrador'" soloPunto />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate {{ $vencida ? 'text-red-700' : 'text-gray-800' }}">{{ $tarea['nombre'] }}</p>
                    <p class="text-[11px] text-gray-500 truncate">
                        {{ collect([$tarea['user'] ?? null, $tarea['area'] ?? null])->filter()->join(' · ') ?: '—' }}
                        @if($tarea['fecha'])
                            · <span class="{{ $vencida ? 'font-bold text-red-600' : '' }}">{{ \Carbon\Carbon::parse($tarea['fecha'])->format('d/m/Y') }}{{ $vencida ? ' · vencida' : '' }}</span>
                        @endif
                    </p>
                </div>
                @if($nueva)
                    <span class="text-[10px] font-bold text-indigo-700">nueva</span>
                @endif
                @foreach($marcas[$tarea['id']] ?? [] as $marca)
                    <span class="text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-2 py-0.5 whitespace-nowrap">{{ $marca }}</span>
                @endforeach

                @if($editando)
                    <button type="button" wire:click="quitar({{ $tarea['id'] }})" title="Quitar" @disabled($bloqueada)
                        class="text-gray-300 hover:text-red-500 transition-colors shrink-0 disabled:opacity-30 disabled:hover:text-gray-300 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @else
                    <div class="shrink-0 flex items-center gap-1.5"
                         title="{{ $cuenta ? 'Cuenta para el avance del plan' : 'No cuenta para el avance hasta que la tarea esté aprobada' }}">
                        <div class="w-14 h-1.5 rounded-full bg-gray-200 overflow-hidden {{ $cuenta ? '' : 'opacity-50' }}">
                            <div class="h-full rounded-full {{ $avance === 100 ? 'bg-green-500' : ($vencida ? 'bg-red-400' : 'bg-amber-500') }}" style="width: {{ $avance }}%"></div>
                        </div>
                        <span class="w-9 text-right text-[11px] font-bold tabular-nums {{ !$cuenta ? 'text-gray-400' : ($avance === 100 ? 'text-green-700' : 'text-gray-700') }}">{{ $avance }}%</span>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-2">Sin tareas asociadas.</p>
        @endforelse

        @if($editando)
            @foreach($quitados as $item)
                <div wire:key="tarea-quitada-{{ $item['id'] }}" class="flex items-center gap-3 px-3 py-2 rounded-xl border border-dashed border-gray-200">
                    <span class="flex-1 text-sm text-gray-400 line-through truncate">{{ $item['nombre'] }}</span>
                    <button type="button" wire:click="agregar({{ $item['id'] }})" class="text-[11px] font-bold text-gray-500 hover:text-indigo-700">Deshacer</button>
                </div>
            @endforeach

            {{-- Tarea nueva: se crea en el acto y queda agregada a la selección. --}}
            @if($creandoTarea)
                <div class="rounded-xl border border-indigo-200 bg-indigo-50/40 p-3 space-y-3">
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-indigo-700">Nueva tarea</p>
                    <div>
                        <label class="{{ $label }}">Nombre</label>
                        <input type="text" wire:model="nuevaNombre" class="{{ $input }}">
                        @error('nuevaNombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $label }}">Fecha límite</label>
                            <input type="date" wire:model="nuevaFecha" class="{{ $input }}">
                            @error('nuevaFecha') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">Avance (%)</label>
                            <input type="number" min="0" max="100" step="5" wire:model="nuevaPorcentaje" class="{{ $input }}">
                            @error('nuevaPorcentaje') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" wire:click="cerrarFormNuevaTarea" class="px-3 py-1.5 text-xs font-semibold text-gray-500 hover:text-gray-800">Cancelar</button>
                        <button type="button" wire:click="guardarNuevaTarea" wire:loading.attr="disabled" wire:target="guardarNuevaTarea"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60">
                            <x-auditoria.spinner class="h-3.5 w-3.5" wire:loading wire:target="guardarNuevaTarea" />
                            Crear y agregar
                        </button>
                    </div>
                </div>
            @endif
        @endif

        <x-slot:pie>
            @if($editando)
                @include('livewire.auditoria.riesgo.show.partials.barra-edicion', [
                    'modo' => $modo, 'diff' => $diffEnCurso, 'agregarLabel' => 'Agregar tarea',
                ])
            @endif
        </x-slot:pie>

        <x-slot:propuestas>
            @foreach($propuestas as $propuesta)
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="tareas" :estadoEntidad="$estadoModelo" />
            @endforeach
        </x-slot:propuestas>
    </x-auditoria.bloque-riesgo>

    @if($modalAbierto)
        <x-auditoria.modal-buscar titulo="Agregar tarea" placeholder="Nombre de la tarea…">
            @if($puedeCrearTarea)
                <button type="button" wire:click="abrirFormNuevaTarea"
                    class="w-full text-left px-4 py-2.5 rounded-xl border border-dashed border-indigo-200 text-sm font-bold text-indigo-700 hover:bg-indigo-50 transition-colors">
                    + Crear una tarea nueva
                </button>
            @endif
            @forelse ($resultados as $tarea)
                @php $tarVencida = $tarea->fecha && $tarea->fecha->lt($hoy) && $tarea->porcentaje_avance < 100; @endphp
                <button type="button" wire:click="agregar({{ $tarea->id }})"
                    class="w-full text-left px-4 py-3 rounded-xl hover:bg-indigo-50 transition-colors border border-gray-100 hover:border-indigo-200">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-sm text-gray-800 truncate">{{ $tarea->nombre }}</span>
                        <x-auditoria.estado-badge :estado="$tarea->estado" size="xs" class="shrink-0" />
                    </div>
                    <p class="mt-0.5 text-[11px] text-gray-500 truncate">
                        <span class="font-bold {{ $tarea->porcentaje_avance === 100 ? 'text-green-700' : 'text-gray-700' }}">{{ $tarea->porcentaje_avance }}%</span>
                        @if($tarea->fecha) · <span class="{{ $tarVencida ? 'font-bold text-red-600' : '' }}">{{ $tarea->fecha->format('d/m/Y') }}</span> @endif
                        @if($tarea->area) · {{ $tarea->area->nombre }} @endif
                    </p>
                </button>
            @empty
                <p class="text-sm text-gray-400 italic px-4 py-3">{{ $busqueda ? 'Sin resultados.' : 'Escribí para buscar tareas.' }}</p>
            @endforelse
        </x-auditoria.modal-buscar>
    @endif
</div>
