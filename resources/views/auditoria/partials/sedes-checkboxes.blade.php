{{--
    Checkboxes de sedes del riesgo (al menos una, D-029) para los formularios de alta y
    de edición del borrador. "Todas" las marca con Alpine local.
    Variables: $sedes (colección), $seleccionadas (ids marcados por defecto).
--}}
@php $marcadas = collect(old('sedes', $seleccionadas))->map(fn ($id) => (int) $id); @endphp
<div x-data="{ marcarTodas() { this.$root.querySelectorAll('input[name=\'sedes[]\']').forEach(c => c.checked = true) } }">
    <div class="flex items-center justify-between mb-2">
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Sedes *</label>
        <button type="button" @click="marcarTodas()" class="text-xs font-semibold text-gray-400 hover:text-indigo-600 transition-colors">
            Todas
        </button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 rounded-xl border @error('sedes') border-red-300 @else border-gray-200 @enderror">
        @foreach ($sedes as $sede)
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" name="sedes[]" value="{{ $sede->id }}" @checked($marcadas->contains($sede->id))
                    class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                {{ $sede->nombre }}
            </label>
        @endforeach
    </div>
    @error('sedes') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
    @error('sedes.*') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
</div>
