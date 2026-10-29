<div>
    <x-auditoria.bloque-riesgo ancla="efectividad" titulo="Efectividad" sujeto="control" :contador="$testeos->count() ?: null"
        subtitulo="Resultado de los testeos de auditoría. Regula cuánto de su mitigación descuenta el control.">

        <x-slot:accion>
            @if($puedeTestear)
                <button type="button" wire:click="abrirModal"
                    class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Registrar testeo
                </button>
            @endif
        </x-slot:accion>

        {{-- Vigente --}}
        @php $ultimo = $testeos->first(); @endphp
        <div class="flex items-end justify-between gap-4 rounded-xl px-4 py-3 {{ $control->efectividad < 100 ? 'bg-amber-50' : 'bg-indigo-50' }}">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest {{ $control->efectividad < 100 ? 'text-amber-700' : 'text-indigo-700' }}">Vigente</p>
                <p class="text-4xl font-black tabular-nums leading-none {{ $control->efectividad < 100 ? 'text-amber-700' : 'text-indigo-700' }}">
                    {{ $control->efectividad }}<span class="text-lg font-bold opacity-60">%</span>
                </p>
            </div>
            <p class="text-right text-[11px] leading-tight text-gray-600">
                @if($ultimo)
                    Último testeo: <strong>{{ $ultimo->created_at->format('d/m/Y') }}</strong><br>
                    por {{ $ultimo->user?->name ?? '—' }}
                @else
                    Sin testeos registrados
                @endif
            </p>
        </div>

        @if($pendiente)
            @php $cambio = $pendiente->data['diff']['campos']['efectividad'] ?? null; @endphp
            <div class="mt-3 rounded-xl border border-dashed border-amber-300 bg-amber-50/60 px-4 py-2.5 text-xs text-amber-900">
                <strong>En propuesta:</strong>
                {{ $cambio['antes'] ?? '—' }}% → <strong>{{ $cambio['despues'] ?? '—' }}%</strong>,
                por {{ $pendiente->user?->name ?? '—' }} el {{ $pendiente->created_at->format('d/m/Y') }}.
                {{ $pendiente->estado_id === \App\Models\Auditoria\Estado::borrador()->id ? 'Espera validación del gerente.' : 'Espera aprobación del comité.' }}
                No cambia el residual hasta que se aplique.
            </div>
        @endif

        {{-- Historial de testeos --}}
        @if($testeos->isNotEmpty())
            <ul class="mt-4 divide-y divide-gray-100">
                @foreach($testeos as $testeo)
                    @php
                        $tipo = $testeo->data['tipo'] ?? null;
                        $cambio = $testeo->data['diff']['campos']['efectividad'] ?? null;
                        $nombreEstado = $testeo->estado?->nombre;
                        [$etiqueta, $color] = match (true) {
                            $tipo === 'testeo' => ['Confirma', 'bg-gray-100 text-gray-600'],
                            $nombreEstado === 'borrado' => ['Rechazado', 'bg-red-50 text-red-700'],
                            $tipo === 'edicion' || isset($testeo->data['activated_by']) => ['Aplicado', 'bg-green-50 text-green-700'],
                            $nombreEstado === 'validado' => ['Espera comité', 'bg-amber-50 text-amber-800'],
                            default => ['Espera validación', 'bg-amber-50 text-amber-800'],
                        };
                    @endphp
                    <li wire:key="testeo-{{ $testeo->id }}" class="py-3 flex items-start gap-3">
                        <span class="shrink-0 w-16 text-center text-sm font-extrabold tabular-nums text-gray-900">
                            @if($cambio)
                                <span class="text-gray-400 font-medium text-xs">{{ $cambio['antes'] }}→</span>{{ $cambio['despues'] }}%
                            @else
                                {{ $testeo->data['efectividad'] ?? '—' }}%
                            @endif
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-800">{{ $testeo->mensaje }}</p>
                            <p class="text-[11px] text-gray-500">
                                {{ $testeo->user?->name ?? '—' }} · {{ $testeo->created_at->format('d/m/Y H:i') }}
                            </p>
                            @if($nombreEstado === 'borrado' && $testeo->motivo_rechazo)
                                <p class="text-[11px] text-red-700 mt-0.5">Motivo del rechazo: {{ $testeo->motivo_rechazo }}</p>
                            @endif
                            @foreach($testeo->getMedia('adjuntos') as $media)
                                <a href="{{ route('auditoria.actualizaciones.adjuntos.download', [$testeo, $media]) }}"
                                   class="mt-1 mr-2 inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-700 hover:underline">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                    {{ $media->file_name }}
                                </a>
                            @endforeach
                        </div>
                        <span class="shrink-0 text-[10px] font-bold rounded-full px-2 py-0.5 {{ $color }}">{{ $etiqueta }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-auditoria.bloque-riesgo>

    {{-- Modal: registrar testeo --}}
    @if($modalAbierto)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 px-4" wire:keydown.escape.window="cerrarModal">
            <form wire:submit="guardar" class="bg-white rounded-2xl shadow-lg max-w-lg w-full">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-gray-900">Registrar testeo de efectividad</h3>
                    <button type="button" wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600" title="Cerrar">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Efectividad resultante (0–100 %)</label>
                        <input type="number" min="0" max="100" wire:model="efectividad"
                            class="w-32 border-gray-200 rounded-lg px-3 py-2 text-sm tabular-nums focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="text-[11px] text-gray-400 mt-1">Vigente: {{ $control->efectividad }}%. Si no cambia, el testeo queda como confirmación.</p>
                        @error('efectividad') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1.5">Resultado / motivo *</label>
                        <textarea wire:model="motivo" rows="3" placeholder="Qué se testeó y qué se encontró…"
                            class="w-full border-gray-200 rounded-lg text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('motivo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
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
                    <button type="button" wire:click="cerrarModal" class="h-9 px-4 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancelar</button>
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
