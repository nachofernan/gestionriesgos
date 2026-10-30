<div>
    <x-auditoria.bloque-riesgo ancla="riesgos" titulo="Riesgos que mitiga" :contador="$riesgos->count() + $ocultos"
        :subtitulo="$mitiga ? 'La asociación se gestiona desde cada riesgo. Lo que mitiga en cada uno se puede ajustar acá.' : 'No descuenta del residual hasta que el control esté aprobado.'">

        @forelse ($riesgos as $riesgo)
            @php
                $nominal = (int) ($riesgo->pivot->mitigacion ?? $control->mitigacion_default);
                $enEdicion = $editandoRiesgoId === $riesgo->id;
            @endphp
            <div wire:key="riesgo-{{ $riesgo->id }}"
                 class="flex items-center gap-3 px-3 py-2.5 rounded-xl border transition-colors
                     {{ $enEdicion ? 'border-indigo-300 bg-indigo-50/40' : 'border-gray-100 bg-gray-50/60 cursor-pointer hover:border-indigo-200 hover:bg-white' }}"
                 @if(!$enEdicion) onclick="Livewire.dispatch('ver-riesgo', {id: {{ $riesgo->id }}})" @endif>
                <x-auditoria.estado-punto :estado="$riesgo->estado" soloPunto />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">
                        {{ $riesgo->nombre }}
                    </p>
                    <p class="text-[11px] text-gray-500 truncate">
                        {{ collect([$riesgo->tipoRiesgo?->nombre, $riesgo->area?->nombre])->filter()->join(' · ') ?: '—' }}
                    </p>
                </div>
                @if($pendientes[$riesgo->id] ?? false)
                    <span class="text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-2 py-0.5 whitespace-nowrap">cambio propuesto</span>
                @endif

                @if($enEdicion)
                    @include('auditoria.partials.checks-relacion', [
                        'accion' => "alternarCheck('%s')",
                        'checks' => $checksEnEdicion,
                        'numero' => \App\Models\Auditoria\Control::numeroRelacion($checksEnEdicion, (int) $control->mitigacion_default),
                        'tope' => $control->mitigacion_default,
                    ])
                    <button type="button" wire:click="cancelar" class="shrink-0 text-[11px] font-semibold text-gray-500 hover:text-gray-800">Cancelar</button>
                    <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar"
                        class="shrink-0 rounded-lg bg-indigo-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-indigo-700 disabled:opacity-60">Guardar</button>
                @else
                    <span class="shrink-0 min-w-[3rem] text-center text-xs font-extrabold rounded-lg px-2 py-1 tabular-nums
                                 {{ $mitiga ? 'text-indigo-700 bg-indigo-50' : 'text-gray-400 bg-white border border-dashed border-gray-200' }}"
                          title="{{ $mitiga ? 'Descuenta del residual de este riesgo' : 'No descuenta hasta que el control esté aprobado' }}">
                        −{{ $riesgo->pivot->mitigacionEfectiva($control->mitigacion_default) }}@if($riesgo->pivot->efectividad < 100) <span class="font-medium opacity-60">de {{ $nominal }} · {{ $riesgo->pivot->efectividad }}%</span>@endif
                    </span>
                    @if(($editables[$riesgo->id] ?? false) && $editandoRiesgoId === null)
                        <button type="button" wire:click="editar({{ $riesgo->id }})" onclick="event.stopPropagation()" title="Ajustar lo que mitiga en este riesgo"
                            class="shrink-0 text-gray-300 hover:text-indigo-600 transition-colors">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.232-6.232a2.5 2.5 0 113.536 3.536L12.536 14.5H9V11z"/></svg>
                        </button>
                    @endif
                @endif
                <span class="shrink-0 w-20 text-right text-xs tabular-nums text-gray-500" title="Valor total → residual">
                    {{ $riesgo->valor_total }} → <strong class="text-gray-900">{{ $riesgo->valor_residual }}</strong>
                </span>
            </div>
        @empty
            @if($ocultos === 0)
                <p class="text-sm text-gray-400 italic py-2">Este control no está asociado a ningún riesgo todavía.</p>
            @endif
        @endforelse

        @error('checks') <p class="text-xs text-red-600 px-1">{{ $message }}</p> @enderror

        @if($ocultos > 0)
            <p class="text-[11px] text-gray-400 italic px-1">
                y {{ $ocultos }} {{ $ocultos === 1 ? 'riesgo' : 'riesgos' }} de otras áreas que no podés ver.
            </p>
        @endif
    </x-auditoria.bloque-riesgo>
</div>
