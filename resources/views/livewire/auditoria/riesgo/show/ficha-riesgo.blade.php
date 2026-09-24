<div>
    @php
        $input = 'w-full border-gray-200 rounded-lg text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400';
        $label = 'block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1';
        $esPropuesta = $modo !== 'directo';
    @endphp

    <x-auditoria.bloque-riesgo ancla="ficha" titulo="Ficha del riesgo"
        :modo="$modo" :gerencias="$gerencias" :editando="$editando" :hayPropuestas="$propuestas->isNotEmpty()">

        <x-slot:accion>
            @if($puedeActualizar && !$editando)
                @if($estadoModelo === 'borrador')
                    <a href="{{ route('auditoria.riesgos.edit', $riesgo) }}"
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
            @if($riesgo->descripcion)
                <p class="text-[15px] leading-relaxed text-gray-700">{{ $riesgo->descripcion }}</p>
            @else
                <p class="text-sm text-gray-400 italic">Sin descripción.</p>
            @endif

            <dl class="mt-3 grid grid-cols-3 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="{{ $label }}">Respuesta</dt>
                    <dd class="font-semibold text-gray-800">{{ $riesgo->respuesta?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="{{ $label }}">Tipo</dt>
                    <dd class="text-gray-800">{{ $riesgo->tipoRiesgo->nombre ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="{{ $label }}">Registrado por</dt>
                    <dd class="text-gray-800 truncate">{{ $riesgo->user->name ?? '—' }} · {{ $riesgo->created_at->format('d/m/Y') }}</dd>
                </div>
                @if($riesgo->fundamento)
                    <div class="col-span-3">
                        <dt class="{{ $label }}">Fundamento de la respuesta</dt>
                        <dd class="text-gray-700 whitespace-pre-line">{{ $riesgo->fundamento }}</dd>
                    </div>
                @endif
            </dl>
        @else
            <div class="grid grid-cols-6 gap-x-4 gap-y-3">
                @php $bloq = fn ($c) => in_array($c, $bloqueados, true); @endphp

                <div class="col-span-6">
                    <label class="{{ $label }}">Nombre</label>
                    <input type="text" wire:model="form.nombre" class="{{ $input }}" @disabled($bloq('nombre'))>
                    @error('form.nombre') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-6">
                    <label class="{{ $label }}">Descripción</label>
                    <textarea wire:model="form.descripcion" rows="3" class="{{ $input }}" @disabled($bloq('descripcion'))></textarea>
                </div>
                <div class="col-span-3">
                    <label class="{{ $label }}">Tipo</label>
                    <select wire:model="form.tipo_riesgo_id" class="{{ $input }}" @disabled($bloq('tipo_riesgo_id'))>
                        @foreach($tiposRiesgo as $id => $nombre)
                            <option value="{{ $id }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('form.tipo_riesgo_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-3">
                    <label class="{{ $label }}">Respuesta</label>
                    <select wire:model="form.respuesta" class="{{ $input }}" @disabled($bloq('respuesta'))>
                        <option value="">—</option>
                        @foreach($respuestas as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    @error('form.respuesta') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-6">
                    <label class="{{ $label }}">Fundamento de la respuesta</label>
                    <textarea wire:model="form.fundamento" rows="2" class="{{ $input }}" @disabled($bloq('fundamento'))></textarea>
                    @error('form.fundamento') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2">
                    <label class="{{ $label }}">Impacto (0-10)</label>
                    <input type="number" min="0" max="10" wire:model="form.impacto" class="{{ $input }}" @disabled($bloq('impacto'))>
                    @error('form.impacto') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2">
                    <label class="{{ $label }}">Probabilidad (0-10)</label>
                    <input type="number" min="0" max="10" wire:model="form.probabilidad" class="{{ $input }}" @disabled($bloq('probabilidad'))>
                    @error('form.probabilidad') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-2 flex items-end">
                    <a href="{{ route('auditoria.riesgos.recalcular', $riesgo) }}" class="text-[11px] font-semibold text-gray-500 hover:text-indigo-700 pb-2.5">
                        o recalcular con el cuestionario →
                    </a>
                </div>
                @if(!empty($bloqueados))
                    <p class="col-span-6 text-[11px] text-amber-700">Los campos grisados ya tienen una propuesta pendiente: se pueden volver a tocar cuando se resuelva.</p>
                @endif
            </div>
        @endif

        <x-slot:pie>
            <div class="space-y-2">
                <div>
                    <label class="{{ $label }}">{{ $esPropuesta ? 'Motivo de la propuesta' : 'Motivo del cambio' }}</label>
                    <input type="text" wire:model="mensaje" placeholder="Queda registrado en la actividad del riesgo" class="{{ $input }}">
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
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="campos" :estadoEntidad="$estadoModelo" :tiposRiesgo="$tiposRiesgo" />
            @endforeach
        </x-slot:propuestas>
    </x-auditoria.bloque-riesgo>
</div>
