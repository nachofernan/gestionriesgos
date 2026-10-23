<div>
    @php
        $input = 'w-full border-gray-200 rounded-lg text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400';
        $label = 'block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1';
        $esPropuesta = $modo !== 'directo';
    @endphp

    <x-auditoria.bloque-riesgo ancla="ficha" titulo="Ficha del riesgo"
        :modo="$modo" :editando="$editando" :hayPropuestas="$propuestas->isNotEmpty() || $propuestasSedes->isNotEmpty() || $propuestasDimensiones->isNotEmpty()">

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
                <div class="col-span-3">
                    <dt class="{{ $label }}">Sedes</dt>
                    <dd class="flex flex-wrap gap-1.5">
                        @if($riesgo->sedes->isEmpty())
                            <span class="text-gray-400 italic">Sin sedes.</span>
                        @elseif($riesgo->sedes->count() === $todasLasSedes->count())
                            <span class="text-xs font-semibold rounded-md px-2 py-0.5 bg-indigo-50 text-indigo-700">Todas las sedes</span>
                        @else
                            @foreach($riesgo->sedes as $sede)
                                <span class="text-xs font-semibold rounded-md px-2 py-0.5 bg-gray-100 text-gray-700">{{ $sede->nombre }}</span>
                            @endforeach
                        @endif
                    </dd>
                </div>
                <div class="col-span-3">
                    <dt class="{{ $label }}">Dimensión</dt>
                    <dd class="flex flex-wrap gap-1.5">
                        @forelse($riesgo->dimensiones as $dimension)
                            <span class="text-xs font-semibold rounded-md px-2 py-0.5 bg-teal-50 text-teal-700">{{ $dimension->nombre }}</span>
                        @empty
                            <span class="text-gray-400 italic">Sin dimensión.</span>
                        @endforelse
                    </dd>
                </div>
                @if($riesgo->fundamento_dimension)
                    <div class="col-span-3">
                        <dt class="{{ $label }}">Fundamento de la dimensión</dt>
                        <dd class="text-gray-700 whitespace-pre-line">{{ $riesgo->fundamento_dimension }}</dd>
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
                    @if($puedeRecalcular)
                        <a href="{{ route('auditoria.riesgos.recalcular', $riesgo) }}" class="text-[11px] font-semibold text-gray-500 hover:text-indigo-700 pb-2.5">
                            o recalcular con el cuestionario →
                        </a>
                    @endif
                </div>
                <div class="col-span-6">
                    <div class="flex items-center justify-between mb-1">
                        <label class="{{ $label }} !mb-0">Sedes</label>
                        @unless($sedesBloqueadas)
                            <button type="button" wire:click="marcarTodasLasSedes" class="text-[11px] font-semibold text-gray-400 hover:text-indigo-600">Todas</button>
                        @endunless
                    </div>
                    <div class="grid grid-cols-2 gap-1.5 rounded-lg border border-gray-200 p-2.5 {{ $sedesBloqueadas ? 'bg-gray-50' : '' }}">
                        @foreach($todasLasSedes as $sede)
                            <label class="flex items-center gap-2 text-sm {{ $sedesBloqueadas ? 'text-gray-400' : 'text-gray-700 cursor-pointer' }}">
                                <input type="checkbox" wire:model="sedes" value="{{ $sede->id }}" @disabled($sedesBloqueadas)
                                    class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50">
                                {{ $sede->nombre }}
                            </label>
                        @endforeach
                    </div>
                    @error('sedes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="col-span-6">
                    <label class="{{ $label }}">Dimensión</label>
                    <div class="grid grid-cols-2 gap-1.5 rounded-lg border border-gray-200 p-2.5 {{ $dimensionesBloqueadas ? 'bg-gray-50' : '' }}">
                        @foreach($todasLasDimensiones as $dimension)
                            <label class="flex items-center gap-2 text-sm {{ $dimensionesBloqueadas ? 'text-gray-400' : 'text-gray-700 cursor-pointer' }}" title="{{ $dimension->ejemplos }}">
                                <input type="checkbox" wire:model.live="dimensiones" value="{{ $dimension->id }}" @disabled($dimensionesBloqueadas)
                                    class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50">
                                {{ $dimension->nombre }}
                            </label>
                        @endforeach
                    </div>
                    @error('dimensiones') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                @if(count($dimensiones) >= 2)
                    <div class="col-span-6">
                        <label class="{{ $label }} !text-red-600">Fundamento de la dimensión *</label>
                        <textarea wire:model="fundamentoDimension" rows="2" class="{{ $input }}" @disabled($dimensionesBloqueadas)
                            placeholder="Por qué el riesgo tiene más de una dimensión..."></textarea>
                        @error('fundamentoDimension') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif
                @if(!empty($bloqueados) || $sedesBloqueadas || $dimensionesBloqueadas)
                    <p class="col-span-6 text-[11px] text-amber-700">Lo grisado ya tiene una propuesta pendiente: se puede volver a tocar cuando se resuelva.</p>
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
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="campos" :estadoEntidad="$estadoModelo" />
            @endforeach
            @foreach($propuestasSedes as $propuesta)
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="sedes" :estadoEntidad="$estadoModelo" />
            @endforeach
            @foreach($propuestasDimensiones as $propuesta)
                <x-auditoria.propuesta-pendiente :propuesta="$propuesta" parte="dimensiones" :estadoEntidad="$estadoModelo" />
            @endforeach
        </x-slot:propuestas>
    </x-auditoria.bloque-riesgo>
</div>
