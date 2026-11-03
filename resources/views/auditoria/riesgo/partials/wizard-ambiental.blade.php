{{--
    Un paso del wizard ambiental (D-043): 'probabilidad' (control + frecuencia) o
    'impacto' (medio + naturaleza + magnitud, más las materialidades). Lo usan
    riesgo/create y riesgo/recalcular. Necesita un x-data con un objeto `ambiental`.
    El total que muestra Alpine es una vista previa: el número lo calcula
    EvaluacionRiesgo en el servidor.
    Recibe: $seccion, $ambiental (config('riesgo_ambiental')) y $valores (respuestas vigentes).
--}}
@php
    $ejes = $seccion === 'probabilidad' ? ['control', 'frecuencia'] : ['medio', 'naturaleza', 'magnitud'];
@endphp

<p class="text-sm text-gray-500">
    @if ($seccion === 'probabilidad')
        La <strong>probabilidad</strong> es control + frecuencia (2 a 10).
    @else
        El <strong>impacto</strong> es medio + naturaleza + magnitud (3 a 10), más un extra por las materialidades
        marcadas: ninguna suma 0, de 1 a 10 suman 1, más de 10 suman 2. Nunca pasa de 10.
    @endif
</p>

@foreach ($ejes as $eje)
    @php $definicion = $ambiental[$seccion][$eje]; @endphp
    <div class="border border-gray-100 rounded-xl p-4 @error('ambiental.'.$eje) border-red-300 bg-red-50/30 @enderror">
        <p class="text-sm font-semibold text-gray-700 mb-3">{{ $definicion['titulo'] }}</p>

        @if ($eje === 'magnitud')
            <details class="mb-3 text-xs text-gray-500">
                <summary class="cursor-pointer font-semibold text-gray-600">Qué considerar (I + Ex + Mo + Rv + Ef + Mc)</summary>
                <dl class="mt-2 space-y-1">
                    @foreach ($ambiental['guia_magnitud'] as $variable => $texto)
                        <div><dt class="inline font-semibold text-gray-600">{{ $variable }}:</dt> <dd class="inline">{{ $texto }}</dd></div>
                    @endforeach
                </dl>
            </details>
        @endif

        <div class="space-y-1.5">
            @foreach ($definicion['opciones'] as $o)
                @php
                    // Sin guardar contra null, una respuesta vacía coincidiría con un valor 0.
                    $seleccion = old('ambiental.'.$eje, $valores[$eje] ?? null);
                    $checked = $seleccion !== null && $seleccion == $o['v'];
                @endphp
                <label class="flex items-start gap-3 p-2 rounded-lg border cursor-pointer transition-all"
                       :class="ambiental.{{ $eje }} === {{ $o['v'] }} ? 'border-indigo-200 bg-indigo-50/50' : 'border-gray-100 hover:border-indigo-200 hover:bg-indigo-50/20'">
                    <input type="radio" name="ambiental[{{ $eje }}]" value="{{ $o['v'] }}"
                        {{ $checked ? 'checked' : '' }}
                        x-model.number="ambiental.{{ $eje }}"
                        class="mt-0.5 w-4 h-4 border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <span class="flex-1">
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="text-sm font-semibold text-gray-700">{{ $o['t'] }}</span>
                            <span class="text-xs font-bold text-gray-400 tabular-nums">{{ $o['v'] }}</span>
                        </span>
                        @foreach ($o['d'] as $linea)
                            <span class="block text-xs text-gray-500 leading-5">{{ count($o['d']) > 1 ? '• ' : '' }}{{ $linea }}</span>
                        @endforeach
                    </span>
                </label>
            @endforeach
        </div>
    </div>
@endforeach

@if ($seccion === 'impacto')
    @foreach (['impacto', 'financiera'] as $lista)
        @php $grupo = $ambiental['materialidad'][$lista]; @endphp
        <div class="border border-gray-100 rounded-xl p-4">
            <div class="flex items-baseline justify-between gap-2 mb-1">
                <p class="text-sm font-semibold text-gray-700">{{ $grupo['titulo'] }}</p>
                <p class="text-xs text-gray-400"><span x-text="ambiental.materialidad_{{ $lista }}.length"></span> marcadas</p>
            </div>
            @isset($grupo['pregunta'])
                <p class="text-xs text-gray-500 mb-2">{{ $grupo['pregunta'] }}</p>
            @endisset
            <div class="grid sm:grid-cols-2 gap-1">
                @foreach ($grupo['items'] as $id => $texto)
                    <label class="flex items-start gap-2 p-1.5 rounded-md cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="ambiental[materialidad_{{ $lista }}][]" value="{{ $id }}"
                            @checked(in_array($id, array_map('intval', (array) old('ambiental.materialidad_'.$lista, $valores['materialidad_'.$lista] ?? []))))
                            x-model.number="ambiental.materialidad_{{ $lista }}"
                            class="mt-0.5 w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span class="text-xs text-gray-700 leading-5">{{ $texto }}</span>
                    </label>
                @endforeach
            </div>
            @error('ambiental.materialidad_'.$lista.'.*') <p class="text-xs text-red-500 mt-1 font-medium">{{ $message }}</p> @enderror
        </div>
    @endforeach
    <p class="text-xs text-gray-500">
        Extra por materialidades: <strong class="text-gray-700" x-text="'+' + extraMaterialidad"></strong>
        (<span x-text="ambiental.materialidad_impacto.length + ambiental.materialidad_financiera.length"></span> marcadas en total)
    </p>
@endif
