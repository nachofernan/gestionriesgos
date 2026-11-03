{{--
    Cómo se llegó a impacto y probabilidad: las respuestas vigentes del wizard
    (riesgos.evaluacion, D-042), según su método (D-043). Sólo lee: los números
    que muestra al lado de cada respuesta son los de la config, y los totales
    son los del riesgo, no se recalculan acá.
--}}
@php
    $evaluacion = $riesgo->evaluacion;
    $metodoGuardado = $evaluacion['metodo'] ?? null;
    $r = $evaluacion['respuestas'] ?? [];
    // Un riesgo ambiental de antes de D-043 puede tener respuestas del cuestionario clásico.
    $deOtroMetodo = $metodoGuardado !== null && $metodoGuardado !== $riesgo->metodoEvaluacion()->value;
    $opcion = fn (array $opciones, $valor) => collect($opciones)->firstWhere('v', $valor)['t'] ?? '—';
@endphp

<details class="mt-4 rounded-lg border border-gray-200 group">
    <summary class="flex items-center justify-between gap-2 cursor-pointer px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-gray-500 hover:text-gray-800">
        <span>Evaluación vigente</span>
        <span class="font-semibold normal-case tracking-normal text-gray-400">Probabilidad {{ $riesgo->probabilidad }} · Impacto {{ $riesgo->impacto }}</span>
    </summary>

    <div class="border-t border-gray-100 px-3 py-3 text-sm">
        @if($evaluacion === null)
            <p class="text-gray-400 italic">
                Sin respuestas registradas: el número viene de la carga inicial o de una edición manual anterior.
                Recalculá con el cuestionario para registrarlas.
            </p>
        @else
            @if($deOtroMetodo)
                <p class="mb-3 text-xs text-amber-700">
                    Estas respuestas son del cuestionario {{ \App\Enums\Auditoria\MetodoEvaluacion::from($metodoGuardado)->label() }}.
                    El tipo de este riesgo se evalúa con el {{ $riesgo->metodoEvaluacion()->label() }}: recalculalo para actualizarlo.
                </p>
            @endif

            @if($metodoGuardado === 'clasica')
                @php $preguntas = config('riesgo_preguntas'); @endphp
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach(['probabilidad' => 'Probabilidad', 'impacto' => 'Impacto'] as $eje => $titulo)
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">{{ $titulo }} = {{ $riesgo->$eje }}</p>
                            <ul class="space-y-1.5">
                                @foreach($preguntas[$eje] as $p)
                                    @php $valor = $r[$eje][$p['id']] ?? null; @endphp
                                    <li class="text-xs leading-5">
                                        <span class="text-gray-500">{{ $p['pregunta'] }}</span>
                                        <span class="block font-semibold text-gray-800">{{ $opcion($p['opciones'], $valor) }} <span class="text-gray-400 font-normal">({{ $valor ?? '—' }})</span></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @else
                @php
                    $ambiental = config('riesgo_ambiental');
                    $fila = fn ($seccion, $eje) => [$ambiental[$seccion][$eje]['titulo'], $opcion($ambiental[$seccion][$eje]['opciones'], $r[$eje] ?? null), $r[$eje] ?? '—'];
                    $extra = \App\Services\Auditoria\EvaluacionRiesgo::extraMaterialidad($r);
                @endphp
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Probabilidad = control + frecuencia</p>
                        <dl class="space-y-1 text-xs">
                            @foreach(['control', 'frecuencia'] as $eje)
                                @php [$titulo, $texto, $valor] = $fila('probabilidad', $eje); @endphp
                                <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ $titulo }}</dt><dd class="font-semibold text-gray-800 text-right">{{ $texto }} <span class="text-gray-400 font-normal">({{ $valor }})</span></dd></div>
                            @endforeach
                        </dl>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Impacto = medio + naturaleza + magnitud + extra (tope 10)</p>
                        <dl class="space-y-1 text-xs">
                            @foreach(['medio', 'naturaleza', 'magnitud'] as $eje)
                                @php [$titulo, $texto, $valor] = $fila('impacto', $eje); @endphp
                                <div class="flex justify-between gap-2"><dt class="text-gray-500">{{ $titulo }}</dt><dd class="font-semibold text-gray-800 text-right">{{ $texto }} <span class="text-gray-400 font-normal">({{ $valor }})</span></dd></div>
                            @endforeach
                            <div class="flex justify-between gap-2"><dt class="text-gray-500">Extra por materialidades</dt><dd class="font-semibold text-gray-800">+{{ $extra }}</dd></div>
                        </dl>
                    </div>
                </div>
                @foreach(['impacto', 'financiera'] as $lista)
                    @php $marcadas = $r['materialidad_'.$lista] ?? []; $grupo = $ambiental['materialidad'][$lista]; @endphp
                    <div class="mt-3">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $grupo['titulo'] }} ({{ count($marcadas) }})</p>
                        @if(empty($marcadas))
                            <p class="text-xs text-gray-400 italic">Ninguna.</p>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($marcadas as $id)
                                    <span class="text-xs rounded-md px-2 py-0.5 bg-emerald-50 text-emerald-800">{{ $grupo['items'][$id] ?? '#'.$id }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        @endif
    </div>
</details>
