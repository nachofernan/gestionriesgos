{{--
    Una propuesta de cambio pendiente, dentro del bloque de riesgo/show al que
    afecta. En relaciones (una propuesta = un elemento) es un solo renglón: qué
    cambia, quién y cuándo, en qué punto está y las acciones. En 'campos' (Ficha)
    lista cada campo antes → después y el motivo. El nombre de un elemento propuesto
    abre su modal de resumen. Las acciones disparan 'resolver-actualizacion', que
    atiende GestionActualizaciones (autoriza y registra el voto); las condiciones
    de estado de cada botón son las mismas que usa el historial.
    Props: propuesta (Actualizacion), parte, estadoEntidad, tiposRiesgo (sólo 'campos').
--}}
@props(['propuesta', 'parte', 'estadoEntidad', 'tiposRiesgo' => []])
@php
    $data = $propuesta->data ?? [];
    $esCampos = $parte === 'campos';
    $diff = $esCampos ? ($data['diff']['campos'] ?? []) : ($data['diff']['relaciones'][$parte] ?? []);

    $esperaComite = $propuesta->estado?->nombre === 'validado';
    $doble = !$esperaComite && $propuesta->requiereDobleValidacion();
    if ($doble) {
        $aFavor = $propuesta->validacionesGerencia->where('aprueba', true);
        $faltan = $propuesta->gerenciasPendientes();
    }
    $situacion = match (true) {
        $esperaComite => 'espera al comité',
        $doble => 'votos '.$aFavor->count().'/'.($aFavor->count() + $faltan->count()),
        default => 'espera validación',
    };
    $situacionTitulo = $doble
        ? 'A favor: '.($aFavor->pluck('area.nombre')->join(', ') ?: '—').' · Faltan: '.($faltan->join(', ') ?: '—')
        : ($esperaComite ? 'Validada por '.($propuesta->validadoPor?->name ?? 'la gerencia') : 'Pendiente de validación de un gerente');

    $evento = ['objetivos' => 'ver-objetivo', 'controles' => 'ver-control', 'planesAccion' => 'ver-plan'][$parte] ?? null;

    $etiquetas = [
        'nombre' => 'Nombre', 'descripcion' => 'Descripción', 'respuesta' => 'Respuesta',
        'fundamento' => 'Fundamento', 'tipo_riesgo_id' => 'Tipo de riesgo',
        'impacto' => 'Impacto', 'probabilidad' => 'Probabilidad',
    ];
    $formatear = function ($campo, $valor) use ($tiposRiesgo) {
        if ($valor === null || $valor === '') {
            return '—';
        }
        return match ($campo) {
            'tipo_riesgo_id' => $tiposRiesgo[$valor] ?? '#'.$valor,
            'respuesta' => \App\Enums\Auditoria\RespuestaRiesgo::tryFrom($valor)?->label() ?? $valor,
            default => is_bool($valor) ? ($valor ? 'Sí' : 'No') : $valor,
        };
    };

    // Relación: los elementos de la propuesta con su operación. Las propuestas por
    // elemento traen uno solo; las de bloque (Gerencias, o anteriores al rediseño) varios.
    $items = [];
    foreach (['agrega', 'quita', 'cambia'] as $clave) {
        foreach ($diff[$clave] ?? [] as $elemento) {
            $items[] = [$clave, $elemento];
        }
    }

    $puedeValidar = $estadoEntidad !== 'borrador' && auth()->user()->can('validar', $propuesta);
    $puedeRechazar = $estadoEntidad !== 'borrador' && auth()->user()->can('rechazar', $propuesta);
    $puedeAprobar = $estadoEntidad === 'aprobado' && auth()->user()->can('aprobar', $propuesta);
    $puedeRetirar = auth()->user()->can('cancelar', $propuesta);
    $hayAcciones = $puedeValidar || $puedeRechazar || $puedeAprobar || $puedeRetirar;
    $btn = 'px-2 py-1 text-[11px] font-bold rounded-md transition-colors';
@endphp

@if(!$esCampos)
    <div wire:key="propuesta-{{ $propuesta->id }}" class="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-white px-3 py-2">
        <div class="flex-1 min-w-0 space-y-0.5">
            @forelse($items as [$op, $item])
                <div class="flex items-center gap-2 min-w-0">
                    <span class="w-5 h-5 shrink-0 inline-flex items-center justify-center rounded-md text-xs font-extrabold
                                 {{ ['agrega' => 'bg-emerald-50 text-emerald-700', 'quita' => 'bg-rose-50 text-rose-700', 'cambia' => 'bg-amber-50 text-amber-700'][$op] }}">
                        {{ ['agrega' => '+', 'quita' => '−', 'cambia' => '~'][$op] }}
                    </span>
                    <p class="text-sm leading-5 truncate {{ $op === 'quita' ? 'text-gray-500 line-through decoration-rose-300' : 'text-gray-800 font-medium' }}">
                        @if($evento && !empty($item['id']))
                            <button type="button" title="Ver resumen" onclick="Livewire.dispatch('{{ $evento }}', {id: {{ (int) $item['id'] }}})"
                                class="hover:text-indigo-700 underline decoration-dotted decoration-gray-300 underline-offset-2">{{ $item['nombre'] ?? '—' }}</button>
                        @else
                            {{ $item['nombre'] ?? '—' }}
                        @endif
                        @if($op === 'agrega' && isset($item['mitigacion']))
                            <span class="ml-1 text-[11px] font-bold text-gray-500">mit. {{ $item['mitigacion'] }}</span>
                        @elseif($op === 'cambia')
                            <span class="ml-1 text-[11px] font-bold text-gray-500">mit. <span class="line-through text-gray-400">{{ $item['mitigacion_antes'] }}</span> → {{ $item['mitigacion_despues'] }}</span>
                        @endif
                    </p>
                </div>
            @empty
                <p class="text-sm text-gray-800">{{ $propuesta->mensaje }}</p>
            @endforelse
            <p class="pl-7 text-[11px] text-gray-400 truncate">
                {{ $propuesta->user?->name ?? '—' }} · <time title="{{ $propuesta->created_at?->format('d/m/Y H:i') }}">{{ $propuesta->created_at?->diffForHumans() }}</time>
                · <span class="font-semibold {{ $esperaComite ? 'text-blue-600' : 'text-amber-700' }}" title="{{ $situacionTitulo }}">{{ $situacion }}</span>
            </p>
        </div>

        @if($hayAcciones)
            <div class="flex items-center gap-0.5 shrink-0">@include('auditoria.partials.propuesta-acciones')</div>
        @endif
    </div>
@else
    <div wire:key="propuesta-{{ $propuesta->id }}" class="rounded-lg border border-amber-200 bg-white px-3 py-2.5">
        <div class="flex items-start justify-between gap-3">
            <p class="text-[11px] text-gray-400 pt-0.5">
                <span class="font-semibold text-gray-600">{{ $propuesta->user?->name ?? '—' }}</span>
                · <time title="{{ $propuesta->created_at?->format('d/m/Y H:i') }}">{{ $propuesta->created_at?->diffForHumans() }}</time>
                · <span class="font-semibold {{ $esperaComite ? 'text-blue-600' : 'text-amber-700' }}" title="{{ $situacionTitulo }}">{{ $situacion }}</span>
            </p>
            @if($hayAcciones)
                <div class="flex items-center gap-0.5 shrink-0">@include('auditoria.partials.propuesta-acciones')</div>
            @endif
        </div>
        <dl class="mt-1.5 space-y-1">
            @foreach($diff as $campo => $cambio)
                <div class="text-xs leading-5 grid grid-cols-[6.5rem_1fr] gap-2">
                    <dt class="font-semibold text-gray-500">{{ $etiquetas[$campo] ?? ucfirst(str_replace('_', ' ', $campo)) }}</dt>
                    <dd class="min-w-0">
                        <span class="line-through text-gray-400 decoration-gray-300">{{ \Illuminate\Support\Str::limit($formatear($campo, $cambio['antes']), 80) }}</span>
                        <span class="mx-1 text-amber-500">→</span>
                        <span class="font-semibold text-gray-900">{{ \Illuminate\Support\Str::limit($formatear($campo, $cambio['despues']), 160) }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
        @if($propuesta->mensaje)
            <p class="mt-1.5 text-xs text-gray-500 italic">“{{ $propuesta->mensaje }}”</p>
        @endif
    </div>
@endif
