{{--
    Preguntas de un eje del método clásico (probabilidad: 5 de 0-2; impacto: 10
    de sí/no agrupadas por materialidad, D-045). Lo usan riesgo/create y
    riesgo/recalcular. Necesita un x-data con un objeto `{{ $eje }}`
    (probabilidad o impacto) para el total en vivo.
    Recibe: $eje, $preguntas (las del eje) y $valores (respuestas vigentes, id => valor).
--}}
<p class="text-sm text-gray-500">Estas respuestas determinan {{ $eje === 'impacto' ? 'el' : 'la' }} <strong>{{ $eje }}</strong> del riesgo (0 a 10).</p>
@php $grupoAnterior = null; @endphp
@foreach ($preguntas as $p)
    @if (($p['grupo'] ?? null) !== null && $p['grupo'] !== $grupoAnterior)
        <p class="text-xs font-bold text-indigo-600 uppercase tracking-wider pt-2">{{ $p['grupo'] }}</p>
        @php $grupoAnterior = $p['grupo']; @endphp
    @endif
    @php $binaria = count($p['opciones']) === 2; @endphp
    <div class="border border-gray-100 rounded-xl p-4 @error($eje.'_respuestas.'.$p['id']) border-red-300 bg-red-50/30 @enderror">
        <p class="text-sm font-semibold text-gray-700 mb-3">{{ $p['pregunta'] }}</p>
        <div class="{{ $binaria ? 'flex gap-2' : 'space-y-1.5' }}">
            @foreach ($p['opciones'] as $o)
                @php
                    // Sin guardar contra null, una respuesta vacía (null) coincidiría con la
                    // opción de valor 0 (null == 0 en PHP) y la marcaría por defecto.
                    $seleccion = old($eje.'_respuestas.'.$p['id'], $valores[$p['id']] ?? null);
                    $checked = $seleccion !== null && $seleccion == $o['v'];
                @endphp
                <label class="flex items-center gap-3 p-2 rounded-lg border cursor-pointer transition-all {{ $binaria ? 'flex-1' : '' }}
                    {{ $checked ? 'border-indigo-200 bg-indigo-50/50' : 'border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/20' }}">
                    <input type="radio" name="{{ $eje }}_respuestas[{{ $p['id'] }}]" value="{{ $o['v'] }}"
                        {{ $checked ? 'checked' : '' }}
                        x-model.number="{{ $eje }}[{{ $p['id'] }}]"
                        class="w-4 h-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <span class="text-sm text-gray-700">{{ $o['t'] }}</span>
                </label>
            @endforeach
        </div>
    </div>
@endforeach
@error($eje.'_respuestas') <p class="text-xs text-red-500 font-medium">{{ $message }}</p> @enderror
