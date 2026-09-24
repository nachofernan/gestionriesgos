{{--
    Modal de actividad completa (variante 'timeline'). Se incluye desde
    gestion-actualizaciones-timeline y hereda de ahí $clasificar, $etiquetasCampos,
    $nombresPartes y $anclas. Todo con wire:*, sin x-data (ver conversacion-riesgo.blade.php).
--}}
@php
    $formatear = function (string $campo, $v) use ($tiposRiesgo) {
        if ($v === null || $v === '') return '—';
        if (is_bool($v)) return $v ? 'Sí' : 'No';
        if ($campo === 'tipo_riesgo_id') return $tiposRiesgo[$v] ?? '#'.$v;
        if ($campo === 'respuesta' && is_string($v)) return \App\Enums\Auditoria\RespuestaRiesgo::tryFrom($v)?->label() ?? $v;
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
        return (string) $v;
    };
    $etiqueta = fn ($campo) => $etiquetasCampos[$campo] ?? ucfirst(str_replace('_', ' ', $campo));
    $camposLargos = ['descripcion', 'fundamento'];

    $todasClasificadas = $todas->map(fn ($a) => ['a' => $a] + $clasificar($a));

    $filtros = [
        'todo' => ['Todo', fn ($x) => true],
        'cambios' => ['Cambios', fn ($x) => !$x['esNota']],
        'pendientes' => ['Pendientes', fn ($x) => $x['pendiente']],
        'rechazadas' => ['Rechazadas / retiradas', fn ($x) => $x['grupo'] === 'rechazada'],
        'notas' => ['Notas', fn ($x) => $x['esNota']],
        'adjuntos' => ['Con adjuntos', fn ($x) => $x['a']->getMedia('adjuntos')->isNotEmpty()],
    ];
    $lista = $todasClasificadas
        ->filter($filtros[$filtroActividad][1] ?? fn () => true)
        ->filter(fn ($x) => $parteActividad === '' || in_array($parteActividad, $x['partes']));
    $porDia = $lista->groupBy(fn ($x) => $x['a']->created_at?->toDateString());

    $cambios = $todasClasificadas->reject(fn ($x) => $x['esNota']);
    $conteoTotal = $cambios->countBy('grupo');
    $partesTocadas = $cambios->flatMap(fn ($x) => $x['partes'])->countBy();
    $participantes = $todas->groupBy('user_id')
        ->map(fn ($grupo) => ['user' => $grupo->first()->user, 'cantidad' => $grupo->count(), 'ultima' => $grupo->max('created_at')])
        ->sortByDesc('cantidad');
    $totalAdjuntos = $todas->sum(fn ($a) => $a->getMedia('adjuntos')->count());
    $primera = $todas->min('created_at');
    $ultima = $todas->max('created_at');
@endphp

<div class="fixed inset-0 z-[60] flex items-center justify-center p-2 sm:p-6" wire:keydown.escape.window="cerrarActividad">
    {{-- Bloquea el scroll de fondo mientras el modal existe: la página y el lateral sticky
         (que es su propio contenedor con scroll). El gutter evita que el layout salte al
         desaparecer la barra. --}}
    <style>
        html { overflow: hidden; scrollbar-gutter: stable; }
        aside:has(#actividad) { overflow: hidden !important; }
    </style>
    <button type="button" wire:click="cerrarActividad" class="absolute inset-0 bg-gray-900/40 backdrop-blur-[2px] cursor-default" aria-label="Cerrar"></button>

    <div class="relative w-full max-w-6xl h-full max-h-[92vh] bg-gray-50 rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden">

        {{-- Encabezado --}}
        <header class="shrink-0 px-5 sm:px-6 py-4 bg-white border-b border-gray-200 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-black tracking-tight text-gray-900">Actividad del riesgo</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $cambios->count() }} movimientos · {{ $todas->count() - $cambios->count() }} notas
                    @if($primera) · del {{ $primera->format('d/m/Y') }} al {{ $ultima->format('d/m/Y') }} @endif
                </p>
            </div>
            <button type="button" wire:click="cerrarActividad" title="Cerrar (Esc)"
                class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-xl text-gray-400 hover:text-gray-800 hover:bg-gray-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="flex-1 min-h-0 flex flex-col lg:flex-row">

            {{-- Panel izquierdo: resumen, filtros y participantes --}}
            <aside class="shrink-0 lg:w-72 border-b lg:border-b-0 lg:border-r border-gray-200 bg-white overflow-y-auto max-h-56 lg:max-h-none p-5 space-y-5">

                {{-- Métricas --}}
                <div class="grid grid-cols-2 gap-2">
                    @foreach([
                        ['Aplicadas', $conteoTotal->get('aplicada', 0), 'text-emerald-700'],
                        ['Pendientes', $conteoTotal->get('pendiente', 0), 'text-amber-700'],
                        ['Rechazadas', $conteoTotal->get('rechazada', 0), 'text-red-600'],
                        ['Adjuntos', $totalAdjuntos, 'text-gray-800'],
                    ] as [$rotulo, $valor, $color])
                        <div class="rounded-xl border border-gray-200 px-3 py-2">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $rotulo }}</p>
                            <p class="text-xl font-black tabular-nums {{ $color }}">{{ $valor }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Filtro por tipo --}}
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Mostrar</p>
                    <div class="flex flex-wrap lg:flex-col gap-1">
                        @foreach($filtros as $clave => [$rotulo, $cond])
                            <button type="button" wire:click="$set('filtroActividad', '{{ $clave }}')"
                                @class(['flex items-center justify-between gap-3 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-left transition-colors',
                                        'bg-indigo-50 text-indigo-700' => $filtroActividad === $clave,
                                        'text-gray-600 hover:bg-gray-50' => $filtroActividad !== $clave])>
                                {{ $rotulo }}
                                <span class="tabular-nums text-[11px] opacity-70">{{ $todasClasificadas->filter($cond)->count() }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Filtro por parte tocada --}}
                @if($partesTocadas->isNotEmpty())
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Qué tocó</p>
                        <div class="flex flex-wrap gap-1">
                            <button type="button" wire:click="$set('parteActividad', '')"
                                @class(['px-2 py-1 rounded-md text-[11px] font-semibold', 'bg-gray-900 text-white' => $parteActividad === '', 'bg-gray-100 text-gray-600 hover:bg-gray-200' => $parteActividad !== ''])>
                                Todo
                            </button>
                            @foreach($partesTocadas as $parte => $cantidad)
                                <button type="button" wire:click="$set('parteActividad', '{{ $parte }}')"
                                    @class(['px-2 py-1 rounded-md text-[11px] font-semibold', 'bg-gray-900 text-white' => $parteActividad === $parte, 'bg-gray-100 text-gray-600 hover:bg-gray-200' => $parteActividad !== $parte])>
                                    {{ $nombresPartes[$parte] ?? $parte }} <span class="opacity-60 tabular-nums">{{ $cantidad }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Participantes --}}
                <div class="hidden lg:block">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Participantes</p>
                    <ul class="space-y-2">
                        @foreach($participantes as $p)
                            <li class="flex items-center gap-2.5">
                                <span class="shrink-0 w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-extrabold inline-flex items-center justify-center uppercase">
                                    {{ collect(explode(' ', $p['user']?->name ?? '?'))->take(2)->map(fn ($s) => mb_substr($s, 0, 1))->join('') }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-xs font-semibold text-gray-800 truncate">{{ $p['user']?->name ?? '—' }}</span>
                                    <span class="block text-[10px] text-gray-400 truncate">
                                        {{ $p['user']?->area?->nombre ?? 'Comité' }} · últ. {{ $p['ultima']?->diffForHumans() }}
                                    </span>
                                </span>
                                <span class="text-[11px] font-bold tabular-nums text-gray-500">{{ $p['cantidad'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>

            {{-- Línea de tiempo detallada --}}
            <div class="flex-1 min-h-0 overflow-y-auto px-4 sm:px-6 py-5">
                @forelse($porDia as $dia => $entradas)
                    @php $fecha = \Carbon\Carbon::parse($dia); @endphp
                    <div wire:key="dia-{{ $dia }}" class="mb-6 last:mb-0">
                        <h3 class="mb-2 text-[11px] font-extrabold uppercase tracking-wider text-gray-500">
                            {{ $fecha->isToday() ? 'Hoy' : ($fecha->isYesterday() ? 'Ayer' : $fecha->translatedFormat('l j \d\e F Y')) }}
                        </h3>

                        <ol class="relative space-y-3 before:absolute before:left-[7px] before:top-2 before:bottom-2 before:w-px before:bg-gray-200">
                            @foreach($entradas as $x)
                                @php
                                    $a = $x['a'];
                                    $data = $x['data'];
                                    $diff = $x['diff'];
                                    $enFoco = $focoActividad === $a->id;
                                    $adjuntos = $a->getMedia('adjuntos');
                                    $autor = $a->user;

                                    // Circuito de la entrada, paso por paso.
                                    $pasos = collect();
                                    if (!$x['esNota']) {
                                        $pasos->push(['bg-gray-400', $x['tipo'] === 'creacion' ? 'Creado' : ($x['tipo'] === 'cambio' ? 'Propuesto' : 'Registrado'), $autor?->name, $a->created_at]);
                                        foreach ($a->validacionesGerencia->sortBy('created_at') as $voto) {
                                            $pasos->push([$voto->aprueba ? 'bg-emerald-500' : 'bg-red-500',
                                                ($voto->area?->nombre ?? 'Gerencia').($voto->aprueba ? ' votó a favor' : ' votó en contra'),
                                                $voto->user?->name, $voto->created_at]);
                                        }
                                        if ($a->validado_por_id) $pasos->push(['bg-blue-500', 'Validada', $a->validadoPor?->name, $a->validado_en]);
                                        if ($a->aprobado_por_id) $pasos->push(['bg-emerald-600', 'Aprobada', $a->aprobadoPor?->name, $a->aprobado_en]);
                                        if ($a->rechazado_por_id) $pasos->push(['bg-red-500', 'Rechazada', $a->rechazadoPor?->name, $a->rechazado_en]);
                                        if (!empty($data['activated_by']) && !$a->aprobado_por_id) $pasos->push(['bg-emerald-500', 'Aplicada', $data['activated_by'], null]);
                                        if ($x['estado'] === 'borrado' && !$a->rechazado_por_id) $pasos->push(['bg-gray-400', 'Retirada', null, $a->updated_at]);
                                    }
                                    $faltan = $x['pendiente'] && $a->requiereDobleValidacion() ? $a->gerenciasPendientes() : collect();
                                @endphp
                                <li id="act-det-{{ $a->id }}" wire:key="act-det-{{ $a->id }}" class="relative pl-7 scroll-mt-10">
                                    <span class="absolute left-0 top-4 w-[15px] h-[15px] rounded-full ring-4 ring-gray-50 {{ $x['punto'] }}"></span>

                                    <article @class(['bg-white rounded-xl border shadow-sm transition-shadow',
                                                     'border-indigo-300 ring-2 ring-indigo-200' => $enFoco,
                                                     'border-gray-200' => !$enFoco])>

                                        {{-- Cabecera de la entrada --}}
                                        <div class="px-4 pt-3 pb-2 flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p @class(['text-sm text-gray-900 leading-snug', 'font-bold' => !$x['esNota'], 'whitespace-pre-line' => $x['esNota']])>{{ $a->mensaje }}</p>
                                                <p class="mt-0.5 text-[11px] text-gray-500">
                                                    <span class="font-semibold text-gray-700">{{ $autor?->name ?? '—' }}</span>
                                                    @if($autor?->area) <span class="text-gray-400">({{ $autor->area->nombre }})</span> @endif
                                                    · <time title="{{ $a->created_at?->diffForHumans() }}">{{ $a->created_at?->format('d/m/Y H:i:s') }}</time>
                                                    <span class="text-gray-300">· #{{ $a->id }}</span>
                                                </p>
                                            </div>
                                            <div class="shrink-0 flex flex-col items-end gap-1">
                                                <span class="text-[10px] font-bold uppercase tracking-wide rounded-md px-2 py-0.5 {{ $x['clase'] }}">{{ $x['resultado'] }}</span>
                                                @if($a->estado)
                                                    <x-auditoria.estado-badge :estado="$a->estado" size="sm" />
                                                @endif
                                            </div>
                                        </div>

                                        @if($x['partes'])
                                            <div class="px-4 pb-2 flex flex-wrap gap-1">
                                                @foreach($x['partes'] as $parte)
                                                    @if($x['pendiente'] && isset($anclas[$parte]))
                                                        <a href="#{{ $anclas[$parte] }}" wire:click="cerrarActividad"
                                                           class="text-[10px] font-semibold rounded-md px-1.5 py-0.5 bg-amber-50 text-amber-800 hover:bg-amber-100">
                                                            {{ $nombresPartes[$parte] ?? $parte }} · resolver en su bloque →
                                                        </a>
                                                    @else
                                                        <span class="text-[10px] font-semibold rounded-md px-1.5 py-0.5 bg-gray-100 text-gray-600">{{ $nombresPartes[$parte] ?? $parte }}</span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                        <div class="px-4 pb-3 space-y-3">

                                            {{-- Diff de campos: antes → después --}}
                                            @if(!empty($diff['campos']))
                                                <div class="rounded-lg border border-gray-200 overflow-hidden">
                                                    <table class="w-full text-xs">
                                                        <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-400">
                                                            <tr><th class="text-left font-bold px-3 py-1.5 w-32">Campo</th><th class="text-left font-bold px-3 py-1.5">Antes</th><th class="text-left font-bold px-3 py-1.5">Después</th></tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100">
                                                            @foreach($diff['campos'] as $campo => $cambio)
                                                                <tr class="align-top">
                                                                    <td class="px-3 py-1.5 font-semibold text-gray-600">{{ $etiqueta($campo) }}</td>
                                                                    <td @class(['px-3 py-1.5 text-red-700/80 line-through decoration-red-300', 'whitespace-pre-line' => in_array($campo, $camposLargos)])>{{ $formatear($campo, $cambio['antes'] ?? null) }}</td>
                                                                    <td @class(['px-3 py-1.5 font-semibold text-emerald-800', 'whitespace-pre-line' => in_array($campo, $camposLargos)])>{{ $formatear($campo, $cambio['despues'] ?? null) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @elseif(!empty($data['campos']))
                                                {{-- Snapshot (alta o propuesta sin diff) --}}
                                                <dl class="rounded-lg border border-gray-200 divide-y divide-gray-100 text-xs">
                                                    @foreach($data['campos'] as $campo => $valor)
                                                        <div class="flex gap-3 px-3 py-1.5">
                                                            <dt class="w-32 shrink-0 font-semibold text-gray-600">{{ $etiqueta($campo) }}</dt>
                                                            <dd class="text-gray-800 whitespace-pre-line">{{ $formatear($campo, $valor) }}</dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            @endif

                                            {{-- Diff de relaciones --}}
                                            @foreach($diff['relaciones'] ?? [] as $rel => $cambio)
                                                <div class="rounded-lg border border-gray-200 px-3 py-2">
                                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $nombresPartes[$rel] ?? $rel }}</p>
                                                    <ul class="space-y-0.5 text-xs">
                                                        @foreach(['agrega' => ['+', 'text-emerald-700'], 'quita' => ['−', 'text-red-600'], 'cambia' => ['~', 'text-amber-700']] as $op => [$signo, $color])
                                                            @foreach($cambio[$op] ?? [] as $item)
                                                                @php $extras = collect($item)->except(['id', 'nombre'])->filter(fn ($v) => !is_array($v)); @endphp
                                                                <li class="{{ $color }}">
                                                                    <span class="font-bold">{{ $signo }}</span> {{ $item['nombre'] ?? '—' }}
                                                                    @if($op === 'cambia' && isset($item['mitigacion_antes']))
                                                                        <span class="text-gray-500">— mitigación {{ $item['mitigacion_antes'] }} → <b>{{ $item['mitigacion_despues'] }}</b></span>
                                                                    @elseif($extras->isNotEmpty())
                                                                        <span class="text-gray-500">— {{ $extras->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.(is_bool($v) ? ($v ? 'sí' : 'no') : $v))->join(' · ') }}</span>
                                                                    @endif
                                                                </li>
                                                            @endforeach
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endforeach

                                            {{-- Respuestas del wizard (alta o recálculo) --}}
                                            @if(!empty($data['respuestas']))
                                                <div class="grid sm:grid-cols-2 gap-2">
                                                    @foreach($data['respuestas'] as $dimension => $respuestas)
                                                        <div class="rounded-lg border border-gray-200 px-3 py-2">
                                                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">
                                                                Wizard · {{ ucfirst($dimension) }} <span class="text-gray-600">= {{ array_sum((array) $respuestas) }}</span>
                                                            </p>
                                                            <div class="flex gap-1">
                                                                @foreach((array) $respuestas as $i => $r)
                                                                    <span title="Pregunta {{ $loop->iteration }}"
                                                                        @class(['w-7 h-7 rounded-md inline-flex items-center justify-center text-xs font-bold tabular-nums',
                                                                                'bg-gray-100 text-gray-500' => (int) $r === 0,
                                                                                'bg-amber-100 text-amber-800' => (int) $r === 1,
                                                                                'bg-red-100 text-red-700' => (int) $r >= 2])>{{ $r }}</span>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Circuito --}}
                                            @if($pasos->count() > 1 || $faltan->isNotEmpty())
                                                <div>
                                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Circuito</p>
                                                    <ol class="flex flex-wrap items-center gap-x-1 gap-y-1 text-[11px]">
                                                        @foreach($pasos as [$color, $texto, $quien, $cuando])
                                                            <li class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white pl-1.5 pr-2.5 py-0.5">
                                                                <span class="w-2 h-2 rounded-full {{ $color }}"></span>
                                                                <span class="font-semibold text-gray-800">{{ $texto }}</span>
                                                                <span class="text-gray-500">{{ collect([$quien, $cuando?->format('d/m H:i')])->filter()->join(' · ') }}</span>
                                                            </li>
                                                            @unless($loop->last && $faltan->isEmpty())<li class="text-gray-300">→</li>@endunless
                                                        @endforeach
                                                        @foreach($faltan as $gerencia)
                                                            <li class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-amber-300 bg-amber-50 pl-1.5 pr-2.5 py-0.5">
                                                                <span class="w-2 h-2 rounded-full border border-amber-400"></span>
                                                                <span class="font-semibold text-amber-800">Falta {{ $gerencia }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ol>
                                                </div>
                                            @endif

                                            {{-- Adjuntos --}}
                                            @if($adjuntos->isNotEmpty())
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($adjuntos as $media)
                                                        <a href="{{ route('auditoria.actualizaciones.adjuntos.download', [$a, $media]) }}"
                                                           class="inline-flex items-center gap-1.5 text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-2 py-1 hover:bg-gray-100 max-w-full">
                                                            <svg class="h-3.5 w-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                                                            <span class="truncate">{{ $media->file_name }}</span>
                                                            <span class="text-gray-400 shrink-0">{{ $media->human_readable_size }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Acciones sobre propuestas pendientes --}}
                                            @if($x['pendiente'])
                                                <div class="flex flex-wrap gap-1.5 pt-1 border-t border-gray-100">
                                                    @if($estadoModelo !== 'borrador')
                                                        @can('validar', $a)
                                                            <button wire:click="validarActualizacion({{ $a->id }})" wire:confirm="¿Validar esta actualización?"
                                                                class="mt-2 px-3 py-1 bg-blue-50 text-blue-700 text-[11px] font-bold rounded-lg hover:bg-blue-100">Validar</button>
                                                        @endcan
                                                        @can('rechazar', $a)
                                                            <button wire:click="rechazarActualizacion({{ $a->id }})" wire:confirm="¿Rechazar esta actualización?"
                                                                class="mt-2 px-3 py-1 bg-red-50 text-red-700 text-[11px] font-bold rounded-lg hover:bg-red-100">Rechazar</button>
                                                        @endcan
                                                    @endif
                                                    @if($estadoModelo === 'aprobado')
                                                        @can('aprobar', $a)
                                                            <button wire:click="aprobarActualizacion({{ $a->id }})" wire:confirm="¿Aprobar esta actualización?"
                                                                class="mt-2 px-3 py-1 bg-green-50 text-green-700 text-[11px] font-bold rounded-lg hover:bg-green-100">Aprobar</button>
                                                        @endcan
                                                    @endif
                                                    @can('cancelar', $a)
                                                        <button wire:click="cancelarActualizacion({{ $a->id }})" wire:confirm="¿Retirar esta propuesta?"
                                                            class="mt-2 px-3 py-1 bg-gray-100 text-gray-600 text-[11px] font-bold rounded-lg hover:bg-gray-200">Retirar</button>
                                                    @endcan
                                                </div>
                                            @endif
                                        </div>
                                    </article>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @empty
                    <div class="h-full flex items-center justify-center">
                        <p class="text-sm text-gray-400 italic">Nada que mostrar con este filtro.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
