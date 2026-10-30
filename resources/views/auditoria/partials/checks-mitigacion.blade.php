{{--
    Checks que validan el número de un control (D-040), para formularios HTML.
    Recibe $seleccionados (claves tildadas). El puntaje se muestra en vivo; el
    que vale lo calcula el modelo (Control::puntajeChecks()).
--}}
@php
    $puntos = \App\Models\Auditoria\Control::PUNTOS_POR_CHECK;
    $seleccionados = \App\Models\Auditoria\Control::normalizarChecks($seleccionados ?? []);
@endphp
<div x-data="{ puntaje: {{ count($seleccionados) * $puntos }}, contar() { this.puntaje = this.$root.querySelectorAll('input[name=\'checks[]\']:checked').length * {{ $puntos }} } }">
    <div class="flex items-center justify-between mb-2">
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Mitigación del control</label>
        <span class="text-sm font-black tabular-nums text-indigo-700 bg-indigo-50 rounded-lg px-2.5 py-1"><span x-text="puntaje">{{ count($seleccionados) * $puntos }}</span> / {{ count(\App\Models\Auditoria\Control::CHECKS) * $puntos }}</span>
    </div>
    <div class="space-y-1.5">
        @foreach(\App\Models\Auditoria\Control::CHECKS as $clave => $texto)
            <label class="flex items-center gap-2.5 rounded-xl border border-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" name="checks[]" value="{{ $clave }}" @checked(in_array($clave, $seleccionados)) x-on:change="contar()"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                <span class="flex-1">{{ $texto }}</span>
                <span class="text-[11px] font-bold text-gray-400">+{{ $puntos }}</span>
            </label>
        @endforeach
    </div>
    <p class="text-[11px] text-gray-400 mt-1.5">Cada punto cumplido suma {{ $puntos }}. Es lo máximo que el control puede mitigar en un riesgo.</p>
    @error('checks') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
    @error('checks.*') <p class="text-xs text-red-500 mt-1.5 font-medium">{{ $message }}</p> @enderror
</div>
