{{--
    Panel de referencia del alta y la edición del riesgo, en dos solapas: los tipos de
    riesgo (el script de cada vista resalta el elegido, por los ids ref-tipo-*) y las
    naturalezas con sus ejemplos y referencias (D-032). Tocar una naturaleza en el
    formulario ('naturalezas-cambiaron') pasa a esa solapa y resalta las marcadas.
    Variables: $tiposRiesgo, $naturalezas (con referencias), $naturalezasMarcadas (ids).
--}}
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-3 sticky top-6"
     x-data="{ solapa: 'tipos', marcadas: @js(collect($naturalezasMarcadas)->map(fn ($id) => (string) $id)->values()) }"
     @naturalezas-cambiaron.window="marcadas = $event.detail; solapa = 'naturalezas'">
    <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
        @foreach (['tipos' => 'Tipos de riesgo', 'naturalezas' => 'Naturalezas'] as $clave => $titulo)
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

    <div x-show="solapa === 'naturalezas'" x-cloak class="space-y-2">
        @foreach ($naturalezas as $naturaleza)
            <div class="p-3 rounded-lg border transition-all duration-200"
                :class="marcadas.includes('{{ $naturaleza->id }}') ? 'border-indigo-300 bg-indigo-50/50 shadow-sm' : 'border-gray-100'">
                <p class="text-xs font-bold text-gray-800 mb-1">{{ $naturaleza->nombre }}</p>
                @if ($naturaleza->ejemplos)
                    <p class="text-xs text-gray-500 leading-relaxed"><span class="font-semibold">Ejemplos:</span> {{ $naturaleza->ejemplos }}</p>
                @endif
                @if ($naturaleza->referencias->isNotEmpty())
                    <ul class="mt-2 space-y-1 list-disc pl-4">
                        @foreach ($naturaleza->referencias as $referencia)
                            <li class="text-xs text-gray-600 leading-relaxed">{{ $referencia->texto }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</div>
