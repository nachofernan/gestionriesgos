{{--
    Naturalezas del riesgo (D-032) para el alta y la edición del borrador: al menos una, y
    con dos o más aparece el fundamento, obligatorio. Lee y escribe `naturalezas` (ids como
    string) y `fundamentoNaturaleza` del x-data que la contiene: en el alta, el del wizard
    (que los usa para habilitar "Siguiente"); fuera del wizard ($enWizard = false) los
    declara ella misma. Al cambiar avisa con 'naturalezas-cambiaron' al panel de referencia.
    Variables: $naturalezas (colección), $seleccionadas (ids), $fundamento (?string), $enWizard (bool).
--}}
@php
    $marcadas = collect(old('naturalezas', $seleccionadas))->map(fn ($id) => (string) $id)->values();
    $fundamentoInicial = old('fundamento_naturaleza', $fundamento ?? '') ?? '';
@endphp
<div @unless($enWizard) x-data="{ naturalezas: @js($marcadas), fundamentoNaturaleza: @js($fundamentoInicial) }" @endunless>
    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Naturaleza *</label>
    <div class="space-y-1.5 p-3 rounded-xl border @error('naturalezas') border-red-300 @else border-gray-200 @enderror">
        @foreach ($naturalezas as $naturaleza)
            <label class="flex items-start gap-2.5 px-1 py-1 rounded-lg cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="naturalezas[]" value="{{ $naturaleza->id }}" x-model="naturalezas"
                    @change="$dispatch('naturalezas-cambiaron', [...naturalezas])"
                    class="mt-0.5 w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-gray-800">{{ $naturaleza->nombre }}</span>
                    @if ($naturaleza->ejemplos)
                        <span class="block text-xs text-gray-400 leading-snug line-clamp-1" title="{{ $naturaleza->ejemplos }}">{{ $naturaleza->ejemplos }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
    @error('naturalezas') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
    @error('naturalezas.*') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror

    <div x-show="naturalezas.length >= 2" x-cloak class="mt-3">
        <label class="block text-xs font-bold text-red-600 uppercase tracking-wider mb-2">Fundamento de la naturaleza *</label>
        <textarea name="fundamento_naturaleza" rows="2" x-model="fundamentoNaturaleza"
            class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('fundamento_naturaleza') border-red-300 @enderror"
            placeholder="Por qué el riesgo tiene más de una naturaleza..."></textarea>
        @error('fundamento_naturaleza') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
    </div>
</div>
