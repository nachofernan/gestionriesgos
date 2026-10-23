{{--
    Panel de referencia del alta y la edición del riesgo, en dos solapas: los tipos de
    riesgo (el script de cada vista resalta el elegido, por los ids ref-tipo-*) y las
    dimensiones con sus ejemplos y referencias (D-032). Tocar una dimensión en el
    formulario ('dimensiones-cambiaron') pasa a esa solapa y resalta las marcadas.
    Variables: $tiposRiesgo, $dimensiones (con referencias), $dimensionesMarcadas (ids).
--}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-3 sticky top-6"
     x-data="{ solapa: 'tipos', marcadas: @js(collect($dimensionesMarcadas)->map(fn ($id) => (string) $id)->values()) }"
     @dimensiones-cambiaron.window="marcadas = $event.detail; solapa = 'dimensiones'">
    <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
        @foreach (['tipos' => 'Tipos de riesgo', 'dimensiones' => 'Dimensiones'] as $clave => $titulo)
            <button type="button" @click="solapa = '{{ $clave }}'"
                :class="solapa === '{{ $clave }}' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="flex-1 px-3 py-1.5 text-xs font-bold uppercase tracking-wider rounded-md transition-colors">
                {{ $titulo }}
            </button>
        @endforeach
    </div>

    <div x-show="solapa === 'tipos'" class="space-y-2" id="tipo-riesgo-ref">
        @foreach ($tiposRiesgo as $tipo)
            <div id="ref-tipo-{{ $tipo->id }}"
                class="p-3 rounded-lg border border-gray-100 transition-all duration-200">
                <p class="text-xs font-bold text-gray-800 mb-1">{{ $tipo->nombre }}</p>
                <p class="text-xs text-gray-500 leading-relaxed">{{ $tipo->descripcion }}</p>
            </div>
        @endforeach
    </div>

    <div x-show="solapa === 'dimensiones'" x-cloak class="space-y-2">
        @foreach ($dimensiones as $dimension)
            <div class="p-3 rounded-lg border transition-all duration-200"
                :class="marcadas.includes('{{ $dimension->id }}') ? 'border-indigo-300 bg-indigo-50/50 shadow-sm' : 'border-gray-100'">
                <p class="text-xs font-bold text-gray-800 mb-1">{{ $dimension->nombre }}</p>
                @if ($dimension->ejemplos)
                    <p class="text-xs text-gray-500 leading-relaxed"><span class="font-semibold">Ejemplos:</span> {{ $dimension->ejemplos }}</p>
                @endif
                @if ($dimension->referencias->isNotEmpty())
                    <ul class="mt-2 space-y-1 list-disc pl-4">
                        @foreach ($dimension->referencias as $referencia)
                            <li class="text-xs text-gray-600 leading-relaxed">{{ $referencia->texto }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</div>
