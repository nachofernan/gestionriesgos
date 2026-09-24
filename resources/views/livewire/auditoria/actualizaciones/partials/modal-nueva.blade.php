{{-- Modal de nueva actualización (mensaje, cambios de campo, adjuntos). Lo comparten las variantes 'completa' y 'timeline' del historial. --}}
@if($modalAbierto)
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-lg max-w-lg w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-900">Nueva Actualización</h3>
            <button wire:click="cerrarModal" class="text-gray-400 hover:text-gray-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="px-6 py-4 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mensaje <span class="text-red-500">*</span></label>
                <textarea wire:model="mensaje" rows="3" required
                          placeholder="Describe el cambio o actualización..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                @error('mensaje') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            @if(!empty($camposEditables))
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Cambios propuestos (opcional)</p>
                    <div class="space-y-3">
                        @foreach($camposEditables as $campo => $etiqueta)
                            @php
                                $esFecha = in_array($campo, $camposFecha);
                                $rangoNumerico = $camposNumericos[$campo] ?? null;
                                $opcionesSelect = $camposSelect[$campo] ?? null;
                            @endphp
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $etiqueta }}</label>
                                @if($opcionesSelect)
                                    <select wire:model="cambios.{{ $campo }}"
                                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-gray-50">
                                        <option value="">Dejar sin cambiar</option>
                                        @foreach($opcionesSelect as $valor => $label)
                                            <option value="{{ $valor }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="{{ $esFecha ? 'date' : ($rangoNumerico ? 'number' : 'text') }}"
                                           wire:model="cambios.{{ $campo }}"
                                           @if($rangoNumerico) min="{{ $rangoNumerico['min'] }}" max="{{ $rangoNumerico['max'] }}" step="1" @endif
                                           @unless($esFecha || $rangoNumerico) placeholder="Nuevo valor (dejar vacío para no cambiar)" @endunless
                                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 bg-gray-50">
                                @endif
                                @error('cambios.'.$campo) <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Adjuntos --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Adjuntos (opcional)</label>
                <input type="file" wire:model="archivos" multiple
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="text-xs text-gray-400 mt-1">PDF, Word, Excel o imágenes. Hasta 10&nbsp;MB por archivo.</p>
                <div wire:loading wire:target="archivos" class="text-xs text-indigo-600 mt-1">Subiendo archivos…</div>
                @error('archivos.*') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex gap-3 justify-end">
            <button wire:click="cerrarModal"
                    class="px-4 py-2 text-gray-700 text-sm font-medium hover:bg-gray-100 rounded-lg transition-colors">
                Cancelar
            </button>
            <button wire:click="guardar"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Guardar
            </button>
        </div>
    </div>
</div>
@endif
