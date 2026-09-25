<div>
    @php
        $input = 'w-full border-gray-200 rounded-lg text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400';
        $label = 'block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1';
        $esPropuesta = $modo !== 'directo';
        $bloq = fn ($c) => in_array($c, $bloqueados, true);
    @endphp

    <x-auditoria.bloque-riesgo ancla="ficha" titulo="Ficha de la tarea" sujeto="tarea"
        :modo="$modo" :editando="$editando" :hayPropuestas="$propuestas->isNotEmpty()">

        <x-slot:accion>
            @if($puedeActualizar && !$editando)
                @if($estadoModelo === 'borrador')
                    <a href="{{ route('auditoria.tareas.edit', $tarea) }}"
                       class="inline-flex items-center gap-1.5 text-[11px] font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.232-6.232a2.5 2.5 0 113.536 3.536L12.536 14.5H9V11z"/></svg>
                        Editar
                    </a>
                @else
                    @include('livewire.auditoria.riesgo.show.partials.boton-editar', ['modo' => $modo])
                @endif
            @endif
        </x-slot:accion>

        @if(!$editando)
            @if($tarea->descripcion)
                <p class="text-[15px] leading-relaxed text-gray-700 whitespace-pre-line">{{ $tarea->descripcion }}</p>
            @else
                <p class="text-sm text-gray-400 italic">Sin descripción.</p>
            @endif
        @else
            <div class="grid grid-cols-6 gap-x-4 gap-y-3">
                <div class="col-span-6">
                    <label class="{{ $label }}">Nombre</label>
                    <input type="text" wire:model="form.nombre" class="{{ $input }}" @disabled($bloq('nombre'))>
                    @error('form.nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-6">
                    <label class="{{ $label }}">Descripción</label>
                    <textarea wire:model="form.descripcion" rows="3" class="{{ $input }}" @disabled($bloq('descripcion'))></textarea>
                </div>
                <div class="col-span-2">
                    <label class="{{ $label }}">Fecha límite</label>
                    <input type="date" wire:model="form.fecha" class="{{ $input }}" @disabled($bloq('fecha'))>
                    @error('form.fecha') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2">
                    <label class="{{ $label }}">Avance (%)</label>
                    <input type="number" min="0" max="100" step="5" wire:model="form.porcentaje_avance" class="{{ $input }}" @disabled($bloq('porcentaje_avance'))>
                    @error('form.porcentaje_avance') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <p class="col-span-2 self-end pb-2.5 text-[11px] text-gray-400">Suma al avance del plan sólo si la tarea está aprobada.</p>
                @if(!empty($bloqueados))
                    <p class="col-span-6 text-[11px] text-amber-700">Los campos grisados ya tienen una propuesta pendiente: se pueden volver a tocar cuando se resuelva.</p>
                @endif
            </div>
        @endif

        <x-slot:pie>
            <div class="space-y-2">
                <div>
                    <label class="{{ $label }}">{{ $esPropuesta ? 'Motivo de la propuesta' : 'Motivo del cambio' }}</label>
                    <input type="text" wire:model="mensaje" placeholder="Queda registrado en la actividad de la tarea" class="{{ $input }}">
                    @error('mensaje') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    @error('form') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" wire:click="cancelarEdicion" class="px-3 py-2 text-xs font-semibold text-gray-500 hover:text-gray-800">Cancelar</button>
                    <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                        class="inline-flex items-center gap-1.5 disabled:opacity-60 px-4 py-2 text-xs font-bold rounded-lg text-white transition-colors {{ $esPropuesta ? 'bg-amber-600 hover:bg-amber-700' : 'bg-indigo-600 hover:bg-indigo-700' }}">
                        <x-auditoria.spinner class="h-3.5 w-3.5" wire:loading wire:target="guardar" />
                        {{ $esPropuesta ? 'Enviar propuesta' : 'Guardar' }}
                    </button>
                </div>
            </div>
        </x-slot:pie>

        <x-slot:propuestas>
            @foreach($propuestas as $propuesta)
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="campos" :estadoEntidad="$estadoModelo" />
            @endforeach
        </x-slot:propuestas>
    </x-auditoria.bloque-riesgo>
</div>
