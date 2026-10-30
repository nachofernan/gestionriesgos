<div>
    <x-auditoria.bloque-riesgo ancla="evidencia" titulo="Evidencia de cumplimiento" sujeto="control" :contador="$testeos->count() ?: null"
        subtitulo="Resultado de los testeos de auditoría en cada riesgo. Regula cuánto de su mitigación descuenta el control en ese riesgo.">

        {{-- Vigente por relación --}}
        @forelse($riesgos as $riesgo)
            @php
                $efectividad = (int) $riesgo->pivot->efectividad;
                $ultimo = $ultimos[$riesgo->id] ?? null;
            @endphp
            <div wire:key="evidencia-{{ $riesgo->id }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-gray-50/60">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $riesgo->nombre }}</p>
                    <p class="text-[11px] text-gray-500 truncate">
                        @if($ultimo)
                            Último testeo: {{ $ultimo->fecha->format('d/m/Y') }} · {{ $ultimo->user?->name ?? '—' }}
                        @else
                            Sin testeos registrados
                        @endif
                    </p>
                </div>
                <span class="shrink-0 min-w-[3.5rem] text-center text-sm font-black tabular-nums rounded-lg px-2 py-1 {{ $efectividad < 100 ? 'text-amber-700 bg-amber-50' : 'text-indigo-700 bg-indigo-50' }}">
                    {{ $efectividad }}%
                </span>
                @if($puedeTestear[$riesgo->id] ?? false)
                    <button type="button" wire:click="abrirTesteo({{ $riesgo->id }})"
                        class="shrink-0 inline-flex items-center gap-1 h-7 px-2.5 rounded-lg bg-indigo-600 text-white text-[11px] font-bold hover:bg-indigo-700 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Testear
                    </button>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-400 italic py-2">El control no está asociado a ningún riesgo que puedas ver.</p>
        @endforelse

        @if($control->estado?->nombre !== 'aprobado' && $riesgos->isNotEmpty())
            <p class="mt-2 text-[11px] text-gray-400">Se puede testear cuando el control esté aprobado.</p>
        @endif

        {{-- Historial de testeos --}}
        @if($testeos->isNotEmpty())
            <p class="mt-5 mb-1 text-[10px] font-bold uppercase tracking-widest text-gray-400">Testeos</p>
            <ul class="divide-y divide-gray-100">
                @foreach($testeos as $testeo)
                    <li wire:key="testeo-{{ $testeo->id }}" class="py-3 flex items-start gap-3">
                        <span class="shrink-0 w-20 text-center text-sm font-extrabold tabular-nums text-gray-900">
                            @if($testeo->efectividad_antes !== $testeo->efectividad_despues)
                                <span class="text-gray-400 font-medium text-xs">{{ $testeo->efectividad_antes }}→</span>{{ $testeo->efectividad_despues }}%
                            @else
                                {{ $testeo->efectividad_despues }}%
                            @endif
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-[11px] font-bold text-gray-500 truncate">{{ $testeo->riesgo?->nombre }}</p>
                            <p class="text-sm text-gray-800 whitespace-pre-line">{{ $testeo->comentario }}</p>
                            <p class="text-[11px] text-gray-500">
                                Testeo del {{ $testeo->fecha->format('d/m/Y') }} · {{ $testeo->user?->name ?? '—' }}
                            </p>
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
        @endif
    </x-auditoria.bloque-riesgo>

    {{-- Modal: registrar testeo de una relación --}}
    @if($enTesteo)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" wire:keydown.escape.window="cerrarTesteo">
            <form wire:submit="guardar" class="bg-white rounded-2xl shadow-lg max-w-lg w-full">
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
                               wire:loading.class="pointer-events-none opacity-50" wire:target="archivos,guardar">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                            Adjuntar informe (opcional)
                            <input type="file" wire:model="archivos" multiple class="hidden">
                        </label>
                        <p class="text-[10px] text-gray-400">PDF, Word, Excel o imagen · hasta 10 MB</p>
                    </div>
                </div>

                <div class="px-6 py-3 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" wire:click="cerrarTesteo" class="h-9 px-4 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="guardar,archivos"
                        class="inline-flex items-center gap-1.5 h-9 px-4 rounded-lg bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 disabled:opacity-50">
                        <x-auditoria.spinner class="h-3.5 w-3.5" wire:loading wire:target="guardar" />
                        Registrar testeo
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
