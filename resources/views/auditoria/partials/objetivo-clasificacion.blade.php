{{--
    Clasificación de un objetivo en los forms de alta y edición: estratégico, PEIS y
    contribución a PEIS (D-054). "Es PEIS" sólo se elige al crear ($objetivo null);
    un PEIS no contribuye a otro PEIS, así que el toggle de contribución se oculta.
    Espera $peisDisponibles (los objetivos PEIS elegibles) y, en edición, $objetivo
    con `peis` cargado.
--}}
@php
    $objetivo = $objetivo ?? null;
    $esPeis = (bool) old('es_peis', $objetivo?->es_peis);
    $contribuye = (bool) old('contribuye_peis', $objetivo?->peis->isNotEmpty());
    $peisElegidos = collect(old('peis_ids', $objetivo?->peis->pluck('id') ?? []))->map(fn ($id) => (int) $id);
@endphp
<div x-data="{ esPeis: @js($esPeis), contribuye: @js($contribuye) }">
    <div class="space-y-2.5">
        <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-indigo-200 transition-colors">
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-gray-800">Objetivo estratégico</span>
                <span class="block text-xs text-gray-400 mt-0.5">Marcalo si este objetivo es prioritario dentro de la planificación estratégica.</span>
            </span>
            <input type="hidden" name="estrategico" value="0" />
            <input type="checkbox" name="estrategico" value="1"
                {{ old('estrategico', $objetivo?->estrategico) ? 'checked' : '' }}
                class="sr-only peer" />
            <span class="relative w-11 h-6 shrink-0 bg-gray-200 rounded-full peer-checked:bg-indigo-600 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
        </label>

        @if (! $objetivo)
            <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-amber-200 transition-colors">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-gray-800">Es un objetivo PEIS</span>
                    <span class="block text-xs text-gray-400 mt-0.5">Objetivo del Plan Estratégico de Integridad Sostenible. Otros objetivos le contribuyen y sólo se asocia a riesgos de Corrupción. No se puede cambiar después.</span>
                </span>
                <input type="hidden" name="es_peis" value="0" />
                <input type="checkbox" name="es_peis" value="1" x-model="esPeis"
                    class="sr-only peer" />
                <span class="relative w-11 h-6 shrink-0 bg-gray-200 rounded-full peer-checked:bg-amber-600 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
            </label>
        @elseif ($objetivo->es_peis)
            <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/40 text-sm text-amber-800">
                <span class="font-semibold">Objetivo PEIS.</span>
                <span class="text-amber-700">Otros objetivos le contribuyen y sólo se asocia a riesgos de Corrupción.</span>
            </div>
        @endif

        <label x-show="!esPeis" class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-amber-200 transition-colors">
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-gray-800">Contribuye al PEIS</span>
                <span class="block text-xs text-gray-400 mt-0.5">Este objetivo aporta a uno o más objetivos del Plan Estratégico de Integridad Sostenible.</span>
            </span>
            <input type="hidden" name="contribuye_peis" value="0" />
            <input type="checkbox" name="contribuye_peis" value="1" x-model="contribuye"
                class="sr-only peer" />
            <span class="relative w-11 h-6 shrink-0 bg-gray-200 rounded-full peer-checked:bg-amber-500 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
        </label>
    </div>

    <div x-show="!esPeis && contribuye" x-cloak
         class="mt-3 p-4 rounded-2xl border border-amber-100 bg-amber-50/30">
        <p class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-3">
            Objetivos PEIS a los que contribuye <span class="text-amber-500 font-normal normal-case">— seleccioná al menos uno *</span>
        </p>
        <div class="space-y-2">
            @forelse ($peisDisponibles as $peis)
                <label class="flex items-start gap-3 p-3 rounded-xl border border-amber-100/70 bg-white cursor-pointer transition-colors hover:border-amber-300 hover:bg-amber-50/60 has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50 has-[:checked]:ring-1 has-[:checked]:ring-amber-300">
                    <input type="checkbox" name="peis_ids[]" value="{{ $peis->id }}"
                        {{ $peisElegidos->contains($peis->id) ? 'checked' : '' }}
                        class="mt-0.5 w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500 shrink-0" />
                    <span class="min-w-0 text-sm leading-snug">
                        <span class="block text-gray-800">{{ $peis->nombre }}</span>
                        @if ($peis->descripcion)
                            <span class="block text-xs text-gray-400 mt-0.5">{{ $peis->descripcion }}</span>
                        @endif
                    </span>
                </label>
            @empty
                <p class="text-sm text-gray-500">Todavía no hay objetivos PEIS disponibles.</p>
            @endforelse
        </div>
        @error('peis_ids') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
        @error('peis_ids.*') <p class="text-xs text-red-500 mt-2 font-medium">{{ $message }}</p> @enderror
    </div>
</div>
