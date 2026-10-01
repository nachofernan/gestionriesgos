{{--
    Área + Responsable de los forms de alta/edición. Las opciones y la regla de
    quién puede ser responsable de cada área las arma el controlador
    (Concerns\OpcionesAreaResponsable); acá sólo se filtra el select al cambiar el área.
    Espera: $areas, $usuarios, $responsablesPorArea, $areaSeleccionada, $responsableSeleccionado.
--}}
<div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100"
    x-data="{
        area: @js((string) $areaSeleccionada),
        responsable: @js((string) $responsableSeleccionado),
        porArea: @js((object) $responsablesPorArea),
        usuarios: @js($usuarios->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()),
        get opciones() {
            const ids = this.porArea[this.area] ?? [];
            return this.usuarios.filter(u => ids.includes(u.id));
        },
    }"
    x-init="$watch('area', () => { if (! opciones.some(u => String(u.id) === responsable)) responsable = '' })">
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Área *</label>
        <select name="area_id" x-model="area"
            class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('area_id') border-red-300 @enderror">
            <option value="" disabled>— Elegí un área —</option>
            @foreach ($areas as $area)
                <option value="{{ $area->id }}" @selected($areaSeleccionada == $area->id)>{{ $area->nombre }}</option>
            @endforeach
        </select>
        @error('area_id') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Responsable</label>
        <select name="user_id" x-model="responsable"
            class="w-full border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('user_id') border-red-300 @enderror">
            <option value="">— Sin asignar —</option>
            <template x-for="u in opciones" :key="u.id">
                <option :value="u.id" x-text="u.name" :selected="String(u.id) === responsable"></option>
            </template>
        </select>
        @error('user_id') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
    </div>
</div>
