<div>
    @if($abierto)
        {{-- Backdrop --}}
        <div class="fixed inset-0 z-40 bg-gray-500 bg-opacity-75 transition-opacity"
             wire:click="cancelar"></div>

        {{-- Panel --}}
        <div class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-16 pb-8 overflow-y-auto">
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg"
                 x-data x-on:click.stop>

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-0.5">
                            Confirmar {{ match($accion) { 'validar' => 'validación', 'aprobar' => 'aprobación', default => 'rechazo' } }}
                        </p>
                        <h3 class="text-base font-extrabold text-gray-900 leading-tight">
                            {{ $entidadNombre }}
                        </h3>
                    </div>
                    <button type="button" wire:click="cancelar"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>

                {{-- Mensaje de la actualización --}}
                <div class="px-6 py-4 border-b border-gray-100">
                    <p class="text-sm text-gray-600">{{ $mensaje }}</p>
                </div>

                {{-- Diff de campos, si los hay --}}
                @if(!empty($diff))
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">
                            Cambios propuestos
                        </p>
                        <div class="space-y-1">
                            @foreach($diff as $campo => $cambio)
                                <div class="text-sm text-gray-700 leading-5">
                                    <span class="font-medium">{{ $campo }}:</span>
                                    <span class="line-through opacity-50 ml-1">{{ is_bool($cambio['antes']) ? ($cambio['antes'] ? 'sí' : 'no') : ($cambio['antes'] ?? '—') }}</span>
                                    <span class="mx-1 opacity-40">→</span>
                                    <span class="font-semibold">{{ is_bool($cambio['despues']) ? ($cambio['despues'] ? 'sí' : 'no') : ($cambio['despues'] ?? '—') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="px-6 py-5">
                        <p class="text-sm text-gray-600">
                            ¿Confirmar <strong>{{ $accion }}</strong> de esta actualización?
                        </p>
                    </div>
                @endif

                {{-- Error --}}
                @if($error)
                    <div class="px-6 py-2.5 bg-red-50 border-b border-red-100">
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    </div>
                @endif

                {{-- Footer --}}
                <div class="px-6 py-4 flex items-center justify-end gap-3">
                    <button type="button" wire:click="cancelar"
                            class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                        Cancelar
                    </button>
                    <button type="button"
                            wire:click="confirmar"
                            wire:loading.attr="disabled"
                            wire:target="confirmar"
                            class="px-5 py-2 text-white text-sm font-bold rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed
                                {{ $accion === 'rechazar' ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700' }}">
                        <span wire:loading.remove wire:target="confirmar">
                            Confirmar {{ match($accion) { 'validar' => 'validación', 'aprobar' => 'aprobación', default => 'rechazo' } }}
                        </span>
                        <span wire:loading wire:target="confirmar">
                            Procesando...
                        </span>
                    </button>
                </div>

            </div>
        </div>
    @endif
</div>
