@php
    $estadoValor = match (true) {
        $plan->finalizado => ['Finalizado', 'bg-green-100 text-green-800'],
        $final !== null => ['Evaluado', 'bg-green-100 text-green-800'],
        $estimada === null => ['Sin estimar', 'bg-amber-100 text-amber-800'],
        $plan->estaCompleto() && $mitiga => ['Falta la evaluación final', 'bg-amber-100 text-amber-800'],
        default => ['Estimado', 'bg-indigo-50 text-indigo-700'],
    };
    $par = fn (?array $r) => $r === null ? null : 'P −'.$r['probabilidad'].' · I −'.$r['impacto'];
@endphp
<section id="valor" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 pt-4 pb-3 flex items-center justify-between gap-2">
        <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">Valor del plan</h2>
        <span class="text-[10px] font-bold rounded-full px-2 py-0.5 {{ $estadoValor[1] }}">{{ $estadoValor[0] }}</span>
    </div>

    <dl class="mx-4 text-sm divide-y divide-gray-100">
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Estimado</dt>
            <dd class="font-bold tabular-nums {{ $estimada ? 'text-gray-800' : 'text-gray-400 font-normal' }}">
                {{ $par($estimada) ?? 'Sin estimar' }}
                @if(in_array('evaluacion_estimada', $pendientes, true))
                    <span class="ml-1 text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-2 py-0.5">propuesto</span>
                @endif
            </dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Final</dt>
            <dd class="font-bold tabular-nums {{ $final ? 'text-gray-800' : 'text-gray-400 font-normal' }}">
                {{ $par($final) ?? 'Al terminar el plan' }}
                @if(in_array('evaluacion_final', $pendientes, true))
                    <span class="ml-1 text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-2 py-0.5">propuesta</span>
                @endif
            </dd>
        </div>
        <div class="flex items-center justify-between gap-3 py-1.5">
            <dt class="text-gray-500">Descuenta hoy</dt>
            <dd class="font-bold tabular-nums {{ $mitiga ? 'text-indigo-700' : 'text-gray-400 font-normal' }}"
                title="Según el avance de sus tareas aprobadas. En cada riesgo, con tope en su impacto y su probabilidad.">
                {{ $mitiga ? $par($efectiva) : 'Cuando se apruebe' }}
            </dd>
        </div>
    </dl>
    <p class="mx-4 mt-1 text-[11px] text-gray-400">Cada «sí» reduce 2 puntos su eje, hasta 10. El valor final reemplaza al estimado.</p>

    @if($motivos !== null)
        <div class="mx-4 mt-3 mb-4 space-y-2">
            @foreach(['estimada' => 'Calcular valor del plan', 'final' => 'Evaluación final'] as $tipo => $etiqueta)
                @php $motivo = $motivos[$tipo]; @endphp
                @if($motivo === null)
                    <button type="button" wire:click="abrir('{{ $tipo }}')"
                        class="w-full rounded-xl px-3 py-2 text-sm font-bold transition-colors
                               {{ $tipo === 'final' || $estimada === null ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                        {{ $tipo === 'estimada' && $estimada ? 'Recalcular valor estimado' : $etiqueta }}
                    </button>
                @elseif($tipo === 'final' && $estimada !== null)
                    <p class="text-[11px] text-gray-400">Evaluación final: {{ $motivo }}</p>
                @elseif($tipo === 'estimada')
                    <p class="text-[11px] text-gray-400">{{ $motivo }}</p>
                @endif
            @endforeach
        </div>
    @else
        <div class="mb-4"></div>
    @endif

    {{-- Cuestionario --}}
    @if($abierto)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/40 p-4 sm:p-8" wire:key="cuestionario-{{ $abierto }}">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl" x-on:keydown.escape.window="$wire.cerrar()">
                <div class="px-5 pt-5 pb-3 border-b border-gray-100">
                    <h3 class="text-base font-extrabold text-gray-900">
                        {{ $abierto === 'final' ? '¿El plan de acción implementado en su totalidad logró…?' : '¿Se espera que el plan de acción, una vez implementado en su totalidad, logre…?' }}
                    </h3>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $abierto === 'final' ? 'Da el valor real de mitigación y reemplaza al estimado.' : 'Estima cuánto va a reducir el riesgo una vez terminado.' }}
                        @if($plan->estado?->nombre !== 'borrador') Queda como propuesta hasta que se apruebe. @endif
                    </p>
                </div>

                <div class="px-5 py-4 space-y-5">
                    @foreach(['probabilidad' => 'Reducción de la probabilidad', 'impacto' => 'Reducción del impacto'] as $eje => $titulo)
                        <fieldset>
                            <legend class="flex w-full items-center justify-between text-xs font-extrabold uppercase tracking-wide text-gray-500">
                                <span>{{ $titulo }}</span>
                                <span class="tabular-nums text-indigo-700">−{{ $enCurso[$eje] }} / 10</span>
                            </legend>
                            <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-100">
                                @foreach($preguntas[$eje] as $p)
                                    <div class="flex items-start justify-between gap-4 px-3 py-2.5" wire:key="p-{{ $eje }}-{{ $p['id'] }}">
                                        <p class="text-sm text-gray-700">{{ $p[$abierto] }}</p>
                                        <div class="flex shrink-0 gap-1">
                                            @foreach([1 => 'Sí', 0 => 'No'] as $v => $t)
                                                <label class="cursor-pointer">
                                                    <input type="radio" class="peer sr-only" value="{{ $v }}" wire:model.live="respuestas.{{ $eje }}.{{ $p['id'] }}">
                                                    <span class="inline-block rounded-lg border px-3 py-1 text-xs font-bold text-gray-500 border-gray-200
                                                                 peer-checked:border-indigo-600 peer-checked:bg-indigo-600 peer-checked:text-white">{{ $t }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    @error('respuestas') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    @if($errors->has('respuestas.*'))
                        <p class="text-xs font-medium text-red-600">Respondé todas las preguntas.</p>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 px-5 pb-5">
                    <button type="button" wire:click="cerrar" class="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="button" wire:click="guardar" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                        {{ $plan->estado?->nombre === 'borrador' ? 'Guardar' : 'Proponer' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
