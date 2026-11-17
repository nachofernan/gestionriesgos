@php
    $respuestaTexto = fn ($p, $v) => $v === 1 ? ($p['si_texto'] ?? 'Sí') : ($p['no_texto'] ?? 'No');
    $detalleDe = function ($p) use ($evaluacion, $frecuencias) {
        return match ($p['detalle'] ?? null) {
            'procedimiento' => $evaluacion['procedimiento'] ?? null,
            'sistemas' => collect($evaluacion['sistemas'] ?? [])->push($evaluacion['sistema_otro'] ?? null)->filter()->join(' · ') ?: null,
            'frecuencia' => $frecuencias[$evaluacion['frecuencia'] ?? ''] ?? null,
            default => null,
        };
    };
@endphp
<section id="valor" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 pt-4 pb-3 flex items-center justify-between gap-2">
        <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">Valor del control</h2>
        @if($evaluacion === null)
            <span class="text-[10px] font-bold rounded-full px-2 py-0.5 bg-amber-100 text-amber-800">Sin cuestionario</span>
        @else
            <x-auditoria.clasificacion-control :control="$control" />
        @endif
    </div>

    <div class="mx-4 rounded-xl px-4 py-3 flex items-end justify-between {{ $mitiga ? 'bg-indigo-50' : 'bg-gray-50' }}">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest opacity-80 {{ $mitiga ? 'text-indigo-700' : 'text-gray-500' }}">Puntaje</p>
            <p class="text-4xl font-black tabular-nums leading-none {{ $mitiga ? 'text-indigo-700' : 'text-gray-500' }}">
                {{ $control->puntaje }}<span class="text-sm font-bold opacity-50">/10</span>
            </p>
        </div>
        <p class="text-right text-[11px] leading-tight {{ $mitiga ? 'text-indigo-800' : 'text-gray-500' }}">
            @if(! $operativo)
                Expresión de deseo:<br><strong>mitiga 0</strong>
            @elseif($evaluacion === null)
                Número anterior al cuestionario.<br>Mitiga {{ $control->mitigacion_default }} hasta contestarlo.
            @else
                Mitiga hasta {{ $control->mitigacion_default }}<br>según la evidencia de cada riesgo
            @endif
        </p>
    </div>

    @if($evaluacion !== null)
        <ul class="mx-4 mt-3 space-y-1.5 text-[11px]">
            @foreach($preguntas as $p)
                @php
                    $v = (int) ($evaluacion['respuestas'][$p['id']] ?? 0);
                    $detalle = $v === 1 ? $detalleDe($p) : null;
                @endphp
                <li class="flex items-start gap-2">
                    <span class="shrink-0 w-6 text-right font-bold tabular-nums {{ ($v === 1 ? $p['si'] : $p['no']) > 0 ? 'text-emerald-600' : 'text-gray-300' }}">+{{ $v === 1 ? $p['si'] : $p['no'] }}</span>
                    <span class="flex-1 min-w-0 text-gray-600">
                        {{ $p['texto'] }}
                        <span class="font-semibold text-gray-800">{{ $respuestaTexto($p, $v) }}</span>
                        @if($detalle) <span class="block text-gray-500 truncate" title="{{ $detalle }}">{{ $detalle }}</span> @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    @if($pendiente)
        <p class="mx-4 mt-3 text-[11px] font-semibold text-amber-800 bg-amber-50 rounded-lg px-2.5 py-1.5">Hay una propuesta de cambio del cuestionario esperando validación.</p>
    @endif

    <div class="mx-4 mt-3 mb-4 space-y-2">
        @if($motivo === null)
            <button type="button" wire:click="abrir"
                class="w-full rounded-xl px-3 py-2 text-sm font-bold transition-colors
                       {{ $evaluacion === null ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                {{ $evaluacion === null ? 'Contestar cuestionario' : ($control->estado?->nombre === 'borrador' ? 'Editar respuestas' : 'Proponer cambio de respuestas') }}
            </button>
        @elseif(is_string($motivo) && ! $pendiente && ! $puedeReevaluar)
            <p class="text-[11px] text-gray-400">{{ $motivo }}</p>
        @endif
        @if($puedeReevaluar && ! $pendiente)
            <button type="button" wire:click="abrirReevaluacion"
                class="w-full rounded-xl px-3 py-2 text-sm font-bold bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                Reevaluar (auditoría)
            </button>
            <p class="text-[11px] text-gray-400">Se aplica en el acto, con tu comentario y los archivos que adjuntes.</p>
        @endif
    </div>

    {{-- Cuestionario --}}
    @if($abierto)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/40 p-4 sm:p-8" wire:key="cuestionario-control">
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl" x-on:keydown.escape.window="$wire.cerrar()">
                <div class="px-5 pt-5 pb-3 border-b border-gray-100 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-gray-900">{{ $modo === 'auditor' ? 'Reevaluación del control' : 'Cuestionario del control' }}</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            El puntaje es el valor que el control mitiga en cada riesgo.
                            @if($modo === 'auditor')
                                Se aplica en el acto, sin pasar por el gerente ni el comité.
                            @elseif($control->estado?->nombre !== 'borrador')
                                Queda como propuesta hasta que se apruebe.
                            @endif
                        </p>
                    </div>
                    <span class="shrink-0 text-sm font-black tabular-nums text-indigo-700 bg-indigo-50 rounded-lg px-2.5 py-1">{{ $enCurso }} / 10</span>
                </div>

                <div class="px-5 py-4">
                    <div class="divide-y divide-gray-100 rounded-xl border border-gray-100">
                        @foreach($preguntas as $p)
                            @php $respuesta = $cuestionario['respuestas'][$p['id']] ?? ''; @endphp
                            <div class="px-3 py-2.5" wire:key="pc-{{ $p['id'] }}">
                                <div class="flex items-start justify-between gap-4">
                                    <p class="text-sm text-gray-700">{{ $loop->iteration }}. {{ $p['texto'] }}</p>
                                    <div class="flex shrink-0 gap-1">
                                        @foreach([1, 0] as $v)
                                            <label class="cursor-pointer">
                                                <input type="radio" class="peer sr-only" value="{{ $v }}" wire:model.live="cuestionario.respuestas.{{ $p['id'] }}">
                                                <span class="inline-block rounded-lg border px-3 py-1 text-xs font-bold text-gray-500 border-gray-200 whitespace-nowrap
                                                             peer-checked:border-indigo-600 peer-checked:bg-indigo-600 peer-checked:text-white">
                                                    {{ $respuestaTexto($p, $v) }} <span class="opacity-60">+{{ $v === 1 ? $p['si'] : $p['no'] }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                @if((string) $respuesta === '1')
                                    @switch($p['detalle'] ?? null)
                                        @case('procedimiento')
                                            <input type="text" wire:model="cuestionario.procedimiento" placeholder="¿Cuál? Nombre del procedimiento, instructivo o anexo"
                                                class="mt-2 w-full border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @error('cuestionario.procedimiento') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                            @break
                                        @case('sistemas')
                                            <div class="mt-2 grid sm:grid-cols-2 gap-1">
                                                @foreach($sistemas as $sistema)
                                                    <label class="flex items-start gap-2 text-xs text-gray-700 cursor-pointer">
                                                        <input type="checkbox" value="{{ $sistema }}" wire:model="cuestionario.sistemas"
                                                            class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                        <span>{{ $sistema }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <input type="text" wire:model="cuestionario.sistema_otro" placeholder="Otro sistema (si no está en la lista)"
                                                class="mt-2 w-full border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @error('cuestionario.sistemas') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                            @break
                                        @case('frecuencia')
                                            <select wire:model="cuestionario.frecuencia"
                                                class="mt-2 border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="">Elegí la frecuencia…</option>
                                                @foreach($frecuencias as $clave => $texto)
                                                    <option value="{{ $clave }}">{{ $texto }}</option>
                                                @endforeach
                                            </select>
                                            @error('cuestionario.frecuencia') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                            @break
                                    @endswitch
                                @endif
                                @if($p['id'] === 'operativo' && (string) $respuesta === '0')
                                    <p class="mt-1.5 text-[11px] font-semibold text-red-700">Si el control es una expresión de deseo, mitiga 0 aunque sume puntos.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($modo === 'auditor')
                        <div class="mt-4 space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Comentario</label>
                                <textarea wire:model="comentario" rows="3" placeholder="Por qué cambia la valoración: qué se revisó y qué se encontró."
                                    class="w-full border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                @error('comentario') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Archivos (opcional)</label>
                                <input type="file" wire:model="archivos" multiple class="block w-full text-xs text-gray-600">
                                @foreach($archivos as $i => $archivo)
                                    <p class="mt-1 flex items-center gap-2 text-xs text-gray-700" wire:key="arch-{{ $i }}">
                                        {{ $archivo->getClientOriginalName() }}
                                        <button type="button" wire:click="quitarArchivo({{ $i }})" class="font-semibold text-gray-400 hover:text-red-600">Quitar</button>
                                    </p>
                                @endforeach
                                @error('archivos.*') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    @error('cuestionario') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    @if($errors->has('cuestionario.respuestas.*'))
                        <p class="mt-2 text-xs font-medium text-red-600">Respondé todas las preguntas.</p>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-2 px-5 pb-5">
                    <button type="button" wire:click="cerrar" class="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="button" wire:click="guardar" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">
                        {{ $modo === 'auditor' ? 'Aplicar reevaluación' : ($control->estado?->nombre === 'borrador' ? 'Guardar' : 'Proponer') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</section>
