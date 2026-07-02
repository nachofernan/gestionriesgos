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
                            Confirmar {{ $accion === 'validar' ? 'validación' : 'activación' }}
                        </p>
                        <h3 class="text-base font-extrabold text-gray-900 leading-tight">
                            {{ $nombre }}
                        </h3>
                    </div>
                    <button type="button" wire:click="cancelar"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>

                {{-- Bloqueantes (prerequisitos requeridos) --}}
                @if(!empty($bloqueantes))
                    <div class="px-6 py-4 border-b border-gray-100 bg-amber-50">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="h-4 w-4 text-amber-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-xs font-bold text-amber-700 uppercase tracking-wide">
                                Prerequisitos requeridos
                            </p>
                        </div>
                        <p class="text-xs text-amber-600 mb-3 ml-6">
                            Seleccioná al menos uno para {{ $accion === 'validar' ? 'validar' : 'activar' }} primero.
                        </p>

                        <div class="space-y-2">
                            @foreach($bloqueantes as $item)
                                @php
                                    $key = $item['tipo'] . ':' . $item['id'];
                                    $seleccionado = $seleccionados[$key] ?? false;
                                    $indent = ($item['nivel'] ?? 0) > 0;
                                    $labelTipo = match($item['tipo']) {
                                        'objetivo' => 'Objetivo',
                                        'riesgo'   => 'Riesgo',
                                        default    => ucfirst($item['tipo']),
                                    };
                                @endphp
                                <label class="flex items-start gap-2.5 cursor-pointer {{ $indent ? 'ml-5' : '' }} {{ !$item['puede_validar'] ? 'opacity-50 cursor-not-allowed' : '' }}">
                                    <input type="checkbox"
                                           @if($item['puede_validar'])
                                               wire:click="toggleSeleccion('{{ $key }}')"
                                           @else
                                               disabled
                                           @endif
                                           @checked($seleccionado)
                                           class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500 {{ !$item['puede_validar'] ? 'cursor-not-allowed' : 'cursor-pointer' }}">
                                    <div class="min-w-0">
                                        <span class="text-sm text-gray-700 font-medium">{{ $item['nombre'] }}</span>
                                        <span class="ml-1.5 text-[10px] font-bold text-gray-400 uppercase">{{ $labelTipo }}</span>
                                        @if(!$item['puede_validar'])
                                            <span class="ml-1.5 text-[10px] font-bold text-red-400">Sin permisos</span>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Opcionales --}}
                @if(!empty($opcionales))
                    <div class="px-6 py-4 border-b border-gray-100">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">
                            Opcionales
                        </p>
                        <p class="text-xs text-gray-400 mb-3">
                            Los no seleccionados quedarán en estado borrador.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($opcionales as $item)
                                @php
                                    $key = $item['tipo'] . ':' . $item['id'];
                                    $seleccionado = $seleccionados[$key] ?? false;
                                    $labelTipo = match($item['tipo']) {
                                        'control' => 'Control',
                                        'tarea'   => 'Tarea',
                                        default   => ucfirst($item['tipo']),
                                    };
                                @endphp
                                <label class="flex items-start gap-2.5 cursor-pointer {{ !$item['puede_validar'] ? 'opacity-50 cursor-not-allowed' : '' }}">
                                    <input type="checkbox"
                                           @if($item['puede_validar'])
                                               wire:click="toggleSeleccion('{{ $key }}')"
                                           @else
                                               disabled
                                           @endif
                                           @checked($seleccionado)
                                           class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 {{ !$item['puede_validar'] ? 'cursor-not-allowed' : 'cursor-pointer' }}">
                                    <div class="min-w-0">
                                        <span class="text-sm text-gray-700 truncate block">{{ $item['nombre'] }}</span>
                                        <span class="text-[10px] font-bold text-gray-400 uppercase">{{ $labelTipo }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Resumen de ejecución (solo si hay dependencias) --}}
                @if(!empty($bloqueantes) || !empty($opcionales))
                    @php $resumenItems = $this->resumen(); @endphp
                    @if(!empty($resumenItems))
                        <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">
                                Orden de ejecución
                            </p>
                            <ol class="space-y-1">
                                @foreach($resumenItems as $i => $item)
                                    <li class="flex items-center gap-2 text-sm text-gray-600">
                                        <span class="flex-shrink-0 w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold
                                            {{ isset($item['principal']) ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-500' }}">
                                            {{ $i + 1 }}
                                        </span>
                                        <span class="{{ isset($item['principal']) ? 'font-bold text-gray-800' : '' }}">
                                            {{ $item['nombre'] }}
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif
                @else
                    {{-- Confirmación simple sin dependencias --}}
                    <div class="px-6 py-5">
                        <p class="text-sm text-gray-600">
                            ¿Confirmar <strong>{{ $accion }}</strong> de <strong>{{ $nombre }}</strong>?
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
                            class="px-5 py-2 bg-blue-600 text-white text-sm font-bold rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="confirmar">
                            Confirmar {{ $accion === 'validar' ? 'validación' : 'activación' }}
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
