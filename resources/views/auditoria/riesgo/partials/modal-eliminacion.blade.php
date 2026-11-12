{{--
    Propuesta de eliminación del riesgo (D-047): observaciones obligatorias, un adjunto
    opcional y los controles y planes que se pueden eliminar junto con él. Los datos
    los arma RiesgoController::show() con Riesgo::dependientesEliminables(); los que no
    se pueden elegir aparecen deshabilitados con su motivo. El servidor vuelve a
    validar contra la misma lista en proponerEliminacion().
    Variables: $riesgo, $dependientes (['controles' => [...], 'planes' => [...]]).
--}}
@php
    $conErrores = $errors->hasAny(['observaciones', 'adjunto', 'controles', 'planes']);
    $marcados = fn (string $tipo) => array_map('intval', (array) old($tipo, []));
@endphp
<div x-data="{ menu: false, abierto: @js($conErrores) }" x-on:keydown.escape.window="abierto = false">
    <div class="relative" x-on:click.outside="menu = false">
        <button type="button" x-on:click="menu = !menu" title="Más acciones"
            class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-white border border-gray-200 text-gray-500 hover:text-gray-800 hover:bg-gray-50 transition-colors">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
        </button>
        <div x-show="menu" x-cloak x-transition.origin.top.right
             class="absolute right-0 mt-2 w-52 rounded-xl bg-white border border-gray-200 shadow-lg py-1 z-30">
            <button type="button" x-on:click="menu = false; abierto = true"
                class="w-full text-left px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                Proponer eliminación
            </button>
        </div>
    </div>

    <div x-show="abierto" x-cloak class="fixed inset-0 z-40 bg-gray-500 bg-opacity-75" x-on:click="abierto = false"></div>

    <div x-show="abierto" x-cloak class="fixed inset-0 z-50 flex items-start justify-center px-4 pt-16 pb-8 overflow-y-auto">
        <form action="{{ route('auditoria.riesgos.eliminacion', $riesgo) }}" method="POST" enctype="multipart/form-data"
              class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg text-left" x-on:click.stop>
            @csrf

            <div class="flex items-start justify-between gap-3 px-6 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-xs font-bold text-red-600 uppercase tracking-widest mb-0.5">Proponer eliminación</p>
                    <h3 class="text-base font-extrabold text-gray-900 leading-tight">{{ $riesgo->nombre }}</h3>
                    <p class="mt-1 text-xs text-gray-500">El riesgo sigue vigente hasta que el comité apruebe la eliminación.</p>
                </div>
                <button type="button" x-on:click="abierto = false" class="text-gray-400 hover:text-gray-600 p-1 shrink-0">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>

            <div class="px-6 py-4 space-y-4">
                <div>
                    <label for="eliminacion-observaciones" class="block text-xs font-bold text-gray-700 mb-1">Observaciones <span class="text-red-600">*</span></label>
                    <textarea id="eliminacion-observaciones" name="observaciones" rows="3" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-red-400 focus:ring-red-400"
                        placeholder="Por qué se elimina el riesgo">{{ old('observaciones') }}</textarea>
                    @error('observaciones') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="eliminacion-adjunto" class="block text-xs font-bold text-gray-700 mb-1">Adjunto <span class="font-normal text-gray-400">(opcional)</span></label>
                    <input id="eliminacion-adjunto" type="file" name="adjunto" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                        class="block w-full text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold hover:file:bg-gray-200">
                    @error('adjunto') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                @foreach(['controles' => 'Controles', 'planes' => 'Planes de acción'] as $tipo => $titulo)
                    @if(!empty($dependientes[$tipo]))
                        <fieldset>
                            <legend class="text-xs font-bold text-gray-700">Eliminar también {{ mb_strtolower($titulo) }}</legend>
                            <p class="text-[11px] text-gray-500 mb-1.5">Sólo los que no se asocian a otro riesgo. Cada uno queda como una propuesta aparte.</p>
                            <div class="space-y-1">
                                @foreach($dependientes[$tipo] as $item)
                                    <label class="flex items-start gap-2 rounded-lg border px-3 py-2 {{ $item['seleccionable'] ? 'border-gray-200 hover:bg-gray-50 cursor-pointer' : 'border-dashed border-gray-200 bg-gray-50/60' }}">
                                        <input type="checkbox" name="{{ $tipo }}[]" value="{{ $item['id'] }}"
                                            class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-40"
                                            @checked(in_array($item['id'], $marcados($tipo))) @disabled(!$item['seleccionable'])>
                                        <span class="min-w-0">
                                            <span class="block text-sm {{ $item['seleccionable'] ? 'text-gray-800 font-medium' : 'text-gray-400' }}">{{ $item['nombre'] }}</span>
                                            @if($item['motivo'])
                                                <span class="block text-[11px] text-gray-400">{{ $item['motivo'] }}</span>
                                            @elseif(!empty($item['tareas']))
                                                <span class="block text-[11px] text-gray-500">Se eliminan también sus tareas: {{ implode(', ', $item['tareas']) }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error($tipo) <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </fieldset>
                    @endif
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-100 bg-gray-50/60 rounded-b-2xl">
                <button type="button" x-on:click="abierto = false" class="px-3 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900">Cancelar</button>
                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg bg-red-600 text-white hover:bg-red-700">Enviar propuesta</button>
            </div>
        </form>
    </div>
</div>
