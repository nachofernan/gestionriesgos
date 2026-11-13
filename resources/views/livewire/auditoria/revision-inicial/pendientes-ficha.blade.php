<div>
@if($visible)
    @if($firma)
        <p class="flex items-center gap-1.5 text-xs text-gray-500">
            <svg class="h-4 w-4 text-green-600" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Revisión inicial firmada por <strong class="font-semibold text-gray-700">{{ $firma->firmadoPor?->name ?? '—' }}</strong>
            el {{ $firma->firmado_en->format('d/m/Y') }}
        </p>
    @else
        @php
            $tono = match (true) {
                $errores > 0 => ['caja' => 'border-red-200 bg-red-50/70', 'titulo' => 'text-red-800'],
                $hallazgos->isNotEmpty() => ['caja' => 'border-amber-200 bg-amber-50/70', 'titulo' => 'text-amber-900'],
                default => ['caja' => 'border-green-200 bg-green-50/70', 'titulo' => 'text-green-800'],
            };
            $advertencias = $hallazgos->where('severidad', 'advertencia')->count();
            $punto = ['error' => 'bg-red-500', 'advertencia' => 'bg-amber-400', 'en_curso' => 'bg-sky-400'];
        @endphp
        <section class="rounded-2xl border {{ $tono['caja'] }} px-4 py-3">
            <header class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 class="text-sm {{ $tono['titulo'] }}">
                    <strong class="font-extrabold">Para la revisión inicial</strong>
                    ·
                    @if($errores > 0)
                        {{ $errores === 1 ? '1 error bloquea la firma' : "{$errores} errores bloquean la firma" }}
                    @elseif($advertencias > 0)
                        Sólo advertencias: se firma con comentario
                    @else
                        Lista para firmar
                    @endif
                </h2>
                <div class="flex items-center gap-3 text-[11px] text-gray-500">
                    @if($revision?->revisado_en)
                        <span>Revisado por {{ $revision->revisadoPor?->name ?? '—' }} el {{ $revision->revisado_en->format('d/m/Y') }}</span>
                    @endif
                    @if(Route::has('auditoria.revision-inicial.index'))
                        <a href="{{ route('auditoria.revision-inicial.index', $tipo !== 'riesgo' ? ['tipo' => $tipo] : []) }}"
                           class="font-bold hover:text-indigo-700">Ir a la revisión</a>
                    @endif
                </div>
            </header>

            @if($hallazgos->isNotEmpty())
                <ul class="mt-2 space-y-1.5">
                    @foreach($hallazgos as $h)
                        <li class="flex items-start justify-between gap-3 text-[13px]">
                            <span class="flex items-start gap-2 min-w-0 text-gray-800">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $punto[$h['severidad']] }}"></span>
                                <span>
                                    {{ $h['mensaje'] }}
                                    @if($h['severidad'] === 'advertencia')
                                        <span class="text-[11px] text-amber-700">(advertencia)</span>
                                    @endif
                                </span>
                            </span>
                            @if($h['codigo'] === 'sin_evaluacion' && $h['severidad'] === 'error' && $puedeRecalcular)
                                <a href="{{ route('auditoria.riesgos.recalcular', $elemento) }}"
                                   class="shrink-0 text-[11px] font-bold text-white bg-red-600 hover:bg-red-700 rounded-full px-3 py-1">Recalcular</a>
                            @elseif($h['severidad'] !== 'en_curso')
                                <a href="#{{ $h['ancla'][0] }}"
                                   class="shrink-0 text-[11px] font-bold text-gray-600 bg-white/80 border border-gray-200 rounded-full px-2.5 py-0.5 hover:bg-white">{{ $h['ancla'][1] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Marca los bloques de la ficha donde se arregla cada hallazgo (por su id de ancla).
             Mientras un bloque se edita, manda su propio borde. --}}
        @if($marcas->isNotEmpty() || $faltaRecalcular)
            <style>
                @foreach($marcas as $ancla => $marca)
                    #{{ $ancla }}:not([data-editando]) {
                        border-color: {{ $marca === 'error' ? '#fca5a5' : '#fcd34d' }};
                        box-shadow: 0 0 0 4px {{ $marca === 'error' ? '#fef2f2' : '#fffbeb' }};
                    }
                @endforeach
                @if($faltaRecalcular)
                    #valor [data-revision-recalcular] { display: block; }
                @endif
            </style>
        @endif
    @endif
@endif
</div>
