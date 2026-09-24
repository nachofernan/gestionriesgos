@php
    $paleta = [
        'verde'    => ['txt' => 'text-green-700',  'bg' => 'bg-green-50',  'bar' => 'bg-green-500',  'chip' => 'bg-green-100 text-green-800'],
        'amarillo' => ['txt' => 'text-yellow-700', 'bg' => 'bg-yellow-50', 'bar' => 'bg-yellow-400', 'chip' => 'bg-yellow-100 text-yellow-800'],
        'rojo'     => ['txt' => 'text-red-700',    'bg' => 'bg-red-50',    'bar' => 'bg-red-500',    'chip' => 'bg-red-100 text-red-800'],
    ];
    $cTotal = $paleta[$riesgo->clasificacion_total['color']];
    $cResidual = $paleta[$riesgo->clasificacion_residual['color']];
    // Escala de la barra: el valor máximo posible de un riesgo (impacto 10 + probabilidad 10).
    $escala = 20;
    $pctTotal = min(100, $riesgo->valor_total / $escala * 100);
    $pctResidual = min(100, $riesgo->valor_residual / $escala * 100);
@endphp
{{-- Este x-data es seguro porque no envuelve ningún wire:* (ver conversacion-riesgo.blade.php). --}}
<section id="valor" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
<div x-data="{ proyectado: null }" x-on:residual-actualizado.window="proyectado = $event.detail.valor">

    <div class="px-4 pt-4 pb-3 flex items-center justify-between">
        <h2 class="text-[13px] font-extrabold text-gray-900 tracking-tight">Valor del riesgo</h2>
        @if($puedeActualizar)
            <a href="{{ route('auditoria.riesgos.recalcular', $riesgo) }}"
               class="text-[11px] font-bold text-gray-500 hover:text-indigo-700">Recalcular</a>
        @endif
    </div>

    {{-- Residual, protagonista --}}
    <div class="mx-4 rounded-xl {{ $cResidual['bg'] }} px-4 py-3 flex items-end justify-between">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-widest {{ $cResidual['txt'] }} opacity-80">Residual</p>
            <p class="text-4xl font-black tabular-nums leading-none {{ $cResidual['txt'] }}">{{ $riesgo->valor_residual }}</p>
        </div>
        <div class="text-right">
            <span class="inline-block text-[10px] font-extrabold uppercase tracking-wider rounded-full px-2 py-0.5 {{ $cResidual['chip'] }}">
                {{ $riesgo->clasificacion_residual['etiqueta'] }}
            </span>
            <p class="mt-1 text-[11px] text-gray-500">de {{ $riesgo->valor_total }} ({{ $riesgo->clasificacion_total['etiqueta'] }})</p>
        </div>
    </div>

    {{-- Proyección mientras se editan controles o planes --}}
    <div x-show="proyectado !== null && proyectado !== {{ $riesgo->valor_residual }}" x-cloak
         class="mx-4 mt-2 flex items-center justify-between rounded-lg border border-dashed border-indigo-300 bg-indigo-50/60 px-3 py-1.5 text-xs text-indigo-800">
        <span>Con lo que estás editando</span>
        <span class="font-extrabold tabular-nums">residual <span x-text="proyectado"></span></span>
    </div>

    {{-- Barra: total vs residual --}}
    <div class="mx-4 mt-3">
        <div class="relative h-2 rounded-full bg-gray-100 overflow-hidden">
            <div class="absolute inset-y-0 left-0 {{ $cTotal['bar'] }} opacity-25" style="width: {{ $pctTotal }}%"></div>
            <div class="absolute inset-y-0 left-0 {{ $cResidual['bar'] }}" style="width: {{ $pctResidual }}%"></div>
        </div>
        <div class="mt-1 flex justify-between text-[10px] text-gray-400 tabular-nums"><span>0</span><span>{{ $escala }}</span></div>
    </div>

    {{-- La cuenta --}}
    <dl class="mx-4 mt-3 mb-4 text-sm divide-y divide-gray-100">
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">Impacto</dt>
            <dd class="font-bold text-gray-800 tabular-nums">{{ $riesgo->impacto }}<span class="text-[11px] font-normal text-gray-400">/10</span></dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt class="text-gray-500">+ Probabilidad</dt>
            <dd class="font-bold text-gray-800 tabular-nums">{{ $riesgo->probabilidad }}<span class="text-[11px] font-normal text-gray-400">/10</span></dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt class="font-semibold text-gray-700">= Valor total</dt>
            <dd class="font-extrabold tabular-nums {{ $cTotal['txt'] }}">{{ $riesgo->valor_total }}</dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt><a href="#controles" class="text-gray-500 hover:text-indigo-700">− Controles aprobados</a></dt>
            <dd class="font-bold text-indigo-700 tabular-nums">−{{ $riesgo->mitigacion_controles }}</dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt><a href="#planes" class="text-gray-500 hover:text-indigo-700">− Planes completos</a></dt>
            <dd class="font-bold text-indigo-700 tabular-nums">−{{ $riesgo->mitigacion_planes }}</dd>
        </div>
        <div class="flex items-center justify-between py-1.5">
            <dt class="font-semibold text-gray-700">= Residual</dt>
            <dd class="font-extrabold tabular-nums {{ $cResidual['txt'] }}">{{ $riesgo->valor_residual }}</dd>
        </div>
    </dl>

    @if($propuestasValor->isNotEmpty())
        <a href="#ficha" class="block mx-4 mb-4 rounded-lg border border-dashed border-amber-300 bg-amber-50/70 px-3 py-2 text-xs text-amber-900 hover:bg-amber-50">
            @foreach($propuestasValor as $p)
                @foreach(['impacto' => 'Impacto', 'probabilidad' => 'Probabilidad'] as $campo => $etq)
                    @isset($p->data['diff']['campos'][$campo])
                        <span class="block">Propuesto: {{ $etq }} {{ $p->data['diff']['campos'][$campo]['antes'] }} → <strong>{{ $p->data['diff']['campos'][$campo]['despues'] }}</strong></span>
                    @endisset
                @endforeach
            @endforeach
            <span class="text-[10px] text-amber-700">Todavía no cuenta en el valor · ver en la ficha</span>
        </a>
    @endif
</div>
</section>
