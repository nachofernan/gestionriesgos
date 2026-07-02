@props(['impacto' => 5, 'probabilidad' => 5, 'checked' => false])
<div x-data="{
        impacto: {{ $impacto }},
        probabilidad: {{ $probabilidad }},
        get habilitado() { return (parseInt(this.impacto) + parseInt(this.probabilidad)) >= 14; }
    }"
    x-init="
        $watch('impacto', () => { if (!habilitado) $el.querySelector('input[type=checkbox]').checked = false; });
        $watch('probabilidad', () => { if (!habilitado) $el.querySelector('input[type=checkbox]').checked = false; });
    "
    @input.capture="
        const imp = $el.closest('form').querySelector('[name=impacto]');
        const prob = $el.closest('form').querySelector('[name=probabilidad]');
        if (imp) impacto = imp.value;
        if (prob) probabilidad = prob.value;
    "
    class="rounded-xl border transition-colors"
    :class="habilitado ? 'bg-red-50 border-red-100 p-3' : 'bg-gray-50 border-gray-100 p-3'">
    <input type="hidden" name="mayor_criticidad" value="0" />
    <input type="checkbox" name="mayor_criticidad" id="mayor_criticidad" value="1"
        {{ $checked ? 'checked' : '' }}
        :disabled="!habilitado"
        class="w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-40 disabled:cursor-not-allowed" />
    <label for="mayor_criticidad"
        :class="habilitado ? 'text-red-700 cursor-pointer' : 'text-gray-400 cursor-not-allowed'"
        class="text-sm font-semibold ml-2">
        Marcar como mayor criticidad
        <span x-show="!habilitado" class="text-xs font-normal ml-1">(requiere impacto+probabilidad ≥ 14)</span>
    </label>
</div>
