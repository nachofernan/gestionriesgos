<div>
    <x-auditoria.bloque-riesgo ancla="riesgos" titulo="Riesgos que mitiga" :contador="$riesgos->count() + $ocultos"
        :subtitulo="$mitiga ? 'La asociación se gestiona desde cada riesgo. Lo que mitiga en cada uno y su evidencia de cumplimiento se ajustan acá.' : 'No descuenta del residual hasta que el control esté aprobado.'">

        @forelse ($riesgos as $riesgo)
            @php
                $nominal = (int) ($riesgo->pivot->mitigacion ?? $control->mitigacion_default);
                $efectividad = (int) $riesgo->pivot->efectividad;
                $enEdicion = $editandoRiesgoId === $riesgo->id;
                $testeosRiesgo = $testeos[$riesgo->id] ?? collect();
                $ultimo = $testeosRiesgo->first();
            @endphp
            <div wire:key="riesgo-{{ $riesgo->id }}"
                 class="rounded-xl border transition-colors {{ $enEdicion ? 'border-indigo-300 bg-indigo-50/40' : 'border-gray-100 bg-gray-50/60' }}">

                {{-- Riesgo y lo que mitiga el control en él --}}
                <div class="flex items-center gap-3 px-3 py-2.5 {{ $enEdicion ? '' : 'cursor-pointer rounded-t-xl hover:bg-white' }}"
                     @if(!$enEdicion) onclick="Livewire.dispatch('ver-riesgo', {id: {{ $riesgo->id }}})" @endif>
                    <x-auditoria.estado-punto :estado="$riesgo->estado" soloPunto />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $riesgo->nombre }}</p>
                        <p class="text-[11px] text-gray-500 truncate">
                            {{ collect([$riesgo->tipoRiesgo?->nombre, $riesgo->area?->nombre])->filter()->join(' · ') ?: '—' }}
                        </p>
                    </div>
                    @if($pendientes[$riesgo->id] ?? false)
                        <span class="text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-2 py-0.5 whitespace-nowrap">cambio propuesto</span>
                    @endif

                    @if($enEdicion)
                        <span class="shrink-0 text-[11px] font-bold text-gray-700">
                            mit. <span class="tabular-nums text-indigo-700">{{ \App\Models\Auditoria\Control::numeroRelacion($checksEnEdicion, (int) $control->mitigacion_default) }}</span><span class="font-medium text-gray-400">/{{ $control->mitigacion_default }}</span>
                        </span>
                        <button type="button" wire:click="cancelar" class="shrink-0 text-[11px] font-semibold text-gray-500 hover:text-gray-800">Cancelar</button>
                        <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                            class="shrink-0 rounded-lg bg-indigo-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-indigo-700 disabled:opacity-60">Guardar</button>
                    @else
                        <span class="shrink-0 min-w-[3rem] text-center text-xs font-extrabold rounded-lg px-2 py-1 tabular-nums
                                     {{ $mitiga ? 'text-indigo-700 bg-indigo-50' : 'text-gray-400 bg-white border border-dashed border-gray-200' }}"
                              title="{{ $mitiga ? 'Descuenta del residual de este riesgo' : 'No descuenta hasta que el control esté aprobado' }}">
                            −{{ $riesgo->pivot->mitigacionEfectiva($control->mitigacion_default) }}@if($efectividad < 100) <span class="font-medium opacity-60">de {{ $nominal }}</span>@endif
                        </span>
                        @if(($editables[$riesgo->id] ?? false) && $editandoRiesgoId === null)
                            <button type="button" wire:click="editar({{ $riesgo->id }})" onclick="event.stopPropagation()" title="Ajustar lo que mitiga en este riesgo"
                                class="shrink-0 text-gray-300 hover:text-indigo-600 transition-colors">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.232-6.232a2.5 2.5 0 113.536 3.536L12.536 14.5H9V11z"/></svg>
                            </button>
                        @endif
                    @endif
                    <span class="shrink-0 w-20 text-right text-xs tabular-nums text-gray-500" title="Valor total → residual">
                        {{ $riesgo->valor_total }} → <strong class="text-gray-900">{{ $riesgo->valor_residual }}</strong>
                    </span>
                </div>

                @if($enEdicion)
                    <div class="px-3">
                        @include('auditoria.partials.checks-relacion', [
                            'accion' => "alternarCheck('%s')",
                            'checks' => $checksEnEdicion,
                            'tope' => (int) $control->mitigacion_default,
                        ])
                    </div>
                @endif

                {{-- Evidencia de cumplimiento: % vigente y último testeo --}}
                <div class="flex items-start gap-3 border-t border-gray-100 px-3 py-2">
                    <div class="shrink-0 pt-0.5">
                        <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Evidencia</p>
                        <p class="text-sm font-black tabular-nums {{ $efectividad < 100 ? 'text-amber-700' : 'text-indigo-700' }}">{{ $efectividad }}%</p>
                    </div>
                    <div class="flex-1 min-w-0 text-[11px] text-gray-500">
                        @if($ultimo)
                            <p>
                                Último testeo: <strong class="text-gray-700">{{ $ultimo->fecha->format('d/m/Y') }}</strong> · {{ $ultimo->user?->name ?? '—' }}
                                @if($ultimo->efectividad_antes !== $ultimo->efectividad_despues)
                                    · {{ $ultimo->efectividad_antes }}% → {{ $ultimo->efectividad_despues }}%
                                @else
                                    · confirma {{ $ultimo->efectividad_despues }}%
                                @endif
                            </p>
                            <p class="text-gray-700 line-clamp-2">{{ $ultimo->comentario }}</p>
                            @foreach($ultimo->getMedia('adjuntos') as $media)
                                <a href="{{ route('auditoria.testeos.adjuntos.download', [$ultimo, $media]) }}"
                                   class="mr-2 inline-flex items-center gap-1 font-semibold text-indigo-700 hover:underline">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                    {{ $media->file_name }}
                                </a>
                            @endforeach
                            @if($testeosRiesgo->count() > 1)
                                <button type="button" wire:click="verTesteos({{ $riesgo->id }})" class="font-semibold text-gray-600 hover:text-indigo-700">
                                    Ver los {{ $testeosRiesgo->count() }} testeos
                                </button>
                            @endif
                        @else
                            <p class="pt-1">Sin testeos registrados.</p>
                        @endif
                    </div>
                    @if($puedeTestear[$riesgo->id] ?? false)
                        <button type="button" wire:click="abrirTesteo({{ $riesgo->id }})"
                            class="shrink-0 inline-flex items-center gap-1 h-7 px-2.5 rounded-lg bg-indigo-600 text-white text-[11px] font-bold hover:bg-indigo-700 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Testear
                        </button>
                    @endif
                </div>
            </div>
        @empty
            @if($ocultos === 0)
                <p class="text-sm text-gray-400 italic py-2">Este control no está asociado a ningún riesgo todavía.</p>
            @endif
        @endforelse

        @error('checks') <p class="text-xs text-red-600 px-1">{{ $message }}</p> @enderror

        @if($ocultos > 0)
            <p class="text-[11px] text-gray-400 italic px-1">
                y {{ $ocultos }} {{ $ocultos === 1 ? 'riesgo' : 'riesgos' }} de otras áreas que no podés ver.
            </p>
        @endif
    </x-auditoria.bloque-riesgo>

    {{-- Modal: historial de testeos de una relación --}}
    @if($enHistorial)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" wire:keydown.escape.window="cerrarTesteos">
            <div class="bg-white rounded-2xl shadow-lg max-w-lg w-full max-h-[80vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-base font-extrabold text-gray-900">Testeos</h3>
                        <p class="text-xs text-gray-500 truncate">{{ $control->nombre }} → {{ $enHistorial->nombre }}</p>
                    </div>
                    <button type="button" wire:click="cerrarTesteos" class="text-gray-400 hover:text-gray-600" title="Cerrar">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <ul class="px-6 py-2 divide-y divide-gray-100 overflow-y-auto">
                    @foreach($testeos[$enHistorial->id] ?? [] as $testeo)
                        <li wire:key="testeo-{{ $testeo->id }}" class="py-3 flex items-start gap-3">
                            <span class="shrink-0 w-20 text-center text-sm font-extrabold tabular-nums text-gray-900">
                                @if($testeo->efectividad_antes !== $testeo->efectividad_despues)
                                    <span class="text-gray-400 font-medium text-xs">{{ $testeo->efectividad_antes }}→</span>{{ $testeo->efectividad_despues }}%
                                @else
                                    {{ $testeo->efectividad_despues }}%
                                @endif
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-800 whitespace-pre-line">{{ $testeo->comentario }}</p>
                                <p class="text-[11px] text-gray-500">Testeo del {{ $testeo->fecha->format('d/m/Y') }} · {{ $testeo->user?->name ?? '—' }}</p>
                                @foreach($testeo->getMedia('adjuntos') as $media)
                                    <a href="{{ route('auditoria.testeos.adjuntos.download', [$testeo, $media]) }}"
                                       class="mt-1 mr-2 inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-700 hover:underline">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                        {{ $media->file_name }}
                                    </a>
                                @endforeach
                            </div>
                            <span class="shrink-0 text-[10px] font-bold rounded-full px-2 py-0.5 {{ $testeo->efectividad_antes !== $testeo->efectividad_despues ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $testeo->efectividad_antes !== $testeo->efectividad_despues ? 'Aplicado' : 'Confirma' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Modal: registrar testeo de una relación --}}
    @if($enTesteo)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" wire:keydown.escape.window="cerrarTesteo">
            <form wire:submit="guardarTesteo" class="bg-white rounded-2xl shadow-lg max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-base font-extrabold text-gray-900">Registrar testeo</h3>
                        <p class="text-xs text-gray-500 truncate">{{ $control->nombre }} → {{ $enTesteo->nombre }}</p>
                    </div>
                    <button type="button" wire:click="cerrarTesteo" class="text-gray-400 hover:text-gray-600" title="Cerrar">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div class="flex gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Fecha del testeo *</label>
                            <input type="date" wire:model="fecha" max="{{ now()->toDateString() }}"
                                class="border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('fecha') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Evidencia (0–100 %) *</label>
                            <input type="number" min="0" max="100" wire:model="efectividad"
                                class="w-28 border-gray-200 rounded-lg px-3 py-2 text-sm tabular-nums focus:border-indigo-500 focus:ring-indigo-500">
                            @error('efectividad') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-400 -mt-2">Vigente en este riesgo: {{ (int) $enTesteo->pivot->efectividad }}%. Se aplica en el acto. Si no cambia, el testeo queda como confirmación.</p>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Comentario *</label>
                        <textarea wire:model="comentario" rows="3" placeholder="Qué se testeó y qué se encontró…"
                            class="w-full border-gray-200 rounded-lg text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('comentario') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-2">
                        @if(!empty($archivos))
                            <ul class="space-y-1">
                                @foreach($archivos as $i => $archivo)
                                    <li wire:key="testeo-adj-{{ $i }}" class="flex items-center gap-2 text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-2.5 py-1.5">
                                        <span class="flex-1 truncate">{{ $archivo->getClientOriginalName() }}</span>
                                        <button type="button" wire:click="quitarArchivo({{ $i }})" title="Quitar" class="text-gray-400 hover:text-red-500">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div wire:loading.flex wire:target="archivos" class="items-center gap-2 text-[11px] font-semibold text-indigo-700">
                            <x-auditoria.spinner class="h-3.5 w-3.5" />
                            Subiendo documento…
                        </div>
                        @error('archivos.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-indigo-700 cursor-pointer"
                               wire:loading.class="pointer-events-none opacity-50" wire:target="archivos,guardarTesteo">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                            Adjuntar informe (opcional)
                            <input type="file" wire:model="archivos" multiple class="hidden">
                        </label>
                        <p class="text-[10px] text-gray-400">PDF, Word, Excel o imagen · hasta 10 MB</p>
                    </div>
                </div>

                <div class="px-6 py-3 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" wire:click="cerrarTesteo" class="h-9 px-4 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardarTesteo,archivos"
                        class="inline-flex items-center gap-1.5 h-9 px-4 rounded-lg bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 disabled:opacity-50">
                        <x-auditoria.spinner class="h-3.5 w-3.5" wire:loading wire:target="guardarTesteo" />
                        Registrar testeo
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
