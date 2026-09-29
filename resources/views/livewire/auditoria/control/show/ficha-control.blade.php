<div>
    @php
        $input = 'w-full border-gray-200 rounded-lg text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400';
        $label = 'block text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1';
        $esPropuesta = $modo !== 'directo';
        $bloq = fn ($c) => in_array($c, $bloqueados, true);
        $cantidadAfectados = $afectados->count() + $ocultos;
    @endphp

    <x-auditoria.bloque-riesgo ancla="ficha" titulo="Ficha del control" sujeto="control"
        :modo="$modo" :editando="$editando" :hayPropuestas="$propuestas->isNotEmpty()">

        <x-slot:accion>
            @if($puedeActualizar && !$editando)
                @if($estadoModelo === 'borrador')
                    <a href="{{ route('auditoria.controles.edit', $control) }}"
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
            @if($control->descripcion)
                <p class="text-[15px] leading-relaxed text-gray-700 whitespace-pre-line">{{ $control->descripcion }}</p>
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
                    <label class="{{ $label }}">Mitigación por defecto (1-10)</label>
                    <input type="number" min="1" max="10" wire:model.live.debounce.300ms="form.mitigacion_default" class="{{ $input }}" @disabled($bloq('mitigacion_default'))>
                    @error('form.mitigacion_default') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                @if($cantidadAfectados > 0)
                    <div class="col-span-6 rounded-xl border px-3 py-2.5 transition-colors {{ $propagar ? 'border-indigo-300 bg-indigo-50/60' : 'border-gray-200 bg-gray-50/60' }}">
                        <label class="flex items-start gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" wire:model.live="propagar" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                x-on:click="if ($event.target.checked && !confirm(@js('Vas a llevar la mitigación de este control a '.$form['mitigacion_default'].' en '.($cantidadAfectados === 1 ? 'el riesgo asociado' : "los {$cantidadAfectados} riesgos asociados").', aunque hoy tengan otro valor.'.($esPropuesta ? ' Se aplica cuando se apruebe la propuesta.' : ' Se aplica en el acto.').' ¿Confirmás?'))) $event.preventDefault()">
                            <span>
                                Aplicar también a {{ $cantidadAfectados === 1 ? 'el riesgo asociado' : "los {$cantidadAfectados} riesgos asociados" }}
                                <span class="block text-[11px] text-gray-500">
                                    La mitigación de este control pasa a {{ $form['mitigacion_default'] }} en cada uno, aunque hoy tenga otro valor.
                                    @if($modo !== 'directo') Se aplica cuando se apruebe la propuesta. @endif
                                </span>
                            </span>
                        </label>
                        <ul class="mt-2 pl-6 space-y-0.5 text-xs text-gray-600">
                            @foreach($afectados as $r)
                                <li class="flex items-center gap-2 min-w-0">
                                    @if($r['codigo']) <span class="font-mono text-[10px] font-bold text-gray-400">{{ $r['codigo'] }}</span> @endif
                                    <span class="truncate">{{ $r['nombre'] }}</span>
                                    <span class="ml-auto shrink-0 tabular-nums font-bold {{ $propagar && $r['mitigacion'] != $form['mitigacion_default'] ? 'text-indigo-700' : 'text-gray-400' }}">
                                        @if($propagar && $r['mitigacion'] != $form['mitigacion_default'])
                                            <span class="line-through font-normal text-gray-400">{{ $r['mitigacion'] }}</span> → {{ $form['mitigacion_default'] }}
                                        @else
                                            {{ $r['mitigacion'] }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                            @if($ocultos > 0)
                                <li class="text-gray-400 italic">y {{ $ocultos }} {{ $ocultos === 1 ? 'riesgo' : 'riesgos' }} de otras áreas que no podés ver</li>
                            @endif
                        </ul>
                    </div>
                @endif

                <div class="col-span-6">
                    <label class="flex items-start gap-2 text-sm text-gray-800 {{ $bloq('pausado') ? 'opacity-50' : 'cursor-pointer' }}">
                        <input type="checkbox" wire:model="form.pausado" class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500" @disabled($bloq('pausado'))>
                        <span>
                            Control pausado
                            <span class="block text-[11px] text-gray-500">
                                Mientras esté pausado no descuenta del valor residual de sus riesgos; conserva sus asociaciones y su mitigación.
                                @if($esPropuesta) Se aplica cuando se apruebe la propuesta. @endif
                            </span>
                        </span>
                    </label>
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
                    <input type="text" wire:model="mensaje" placeholder="Queda registrado en la actividad del control" class="{{ $input }}">
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
