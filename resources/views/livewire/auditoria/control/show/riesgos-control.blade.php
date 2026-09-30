<div>
    <x-auditoria.bloque-riesgo ancla="riesgos" titulo="Riesgos que mitiga" :contador="$riesgos->count() + $ocultos"
        :subtitulo="$mitiga ? 'La asociación y su mitigación se gestionan desde cada riesgo.' : ($control->pausado ? 'El control está pausado: no descuenta del residual hasta reanudarlo.' : 'No descuenta del residual hasta que el control esté aprobado.')">

        @forelse ($riesgos as $riesgo)
            @php $mit = $riesgo->pivot->mitigacion ?? $control->mitigacion_default; @endphp
            <div wire:key="riesgo-{{ $riesgo->id }}"
                 class="flex items-center gap-3 px-3 py-2.5 rounded-xl border border-gray-100 bg-gray-50/60 cursor-pointer hover:border-indigo-200 hover:bg-white transition-colors"
                 onclick="Livewire.dispatch('ver-riesgo', {id: {{ $riesgo->id }}})">
                <x-auditoria.estado-punto :estado="$riesgo->estado" soloPunto />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">
                        {{ $riesgo->nombre }}
                    </p>
                    <p class="text-[11px] text-gray-500 truncate">
                        {{ collect([$riesgo->tipoRiesgo?->nombre, $riesgo->area?->nombre])->filter()->join(' · ') ?: '—' }}
                    </p>
                </div>
                <span class="shrink-0 min-w-[3rem] text-center text-xs font-extrabold rounded-lg px-2 py-1 tabular-nums
                             {{ $mitiga ? 'text-indigo-700 bg-indigo-50' : ($control->pausado ? 'text-orange-600 line-through bg-orange-50 border border-dashed border-orange-200' : 'text-gray-400 bg-white border border-dashed border-gray-200') }}"
                      title="{{ $mitiga ? 'Descuenta del residual de este riesgo' : ($control->pausado ? 'El control está pausado' : 'No descuenta hasta que el control esté aprobado') }}">
                    −{{ $mit }}
                </span>
                <span class="shrink-0 w-20 text-right text-xs tabular-nums text-gray-500" title="Valor total → residual">
                    {{ $riesgo->valor_total }} → <strong class="text-gray-900">{{ $riesgo->valor_residual }}</strong>
                </span>
            </div>
        @empty
            @if($ocultos === 0)
                <p class="text-sm text-gray-400 italic py-2">Este control no está asociado a ningún riesgo todavía.</p>
            @endif
        @endforelse

        @if($ocultos > 0)
            <p class="text-[11px] text-gray-400 italic px-1">
                y {{ $ocultos }} {{ $ocultos === 1 ? 'riesgo' : 'riesgos' }} de otras áreas que no podés ver.
            </p>
        @endif
    </x-auditoria.bloque-riesgo>
</div>
