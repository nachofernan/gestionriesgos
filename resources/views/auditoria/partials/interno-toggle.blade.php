{{-- Flag de borrador interno (D-044). Sólo se muestra en formularios de borrador. --}}
<label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-violet-200 transition-colors">
    <span class="min-w-0">
        <span class="flex items-center gap-2 text-sm font-semibold text-gray-800">
            Borrador interno
            <x-auditoria.interno-badge :activo="true" />
        </span>
        <span class="block text-xs text-gray-400 mt-0.5">Gestión propia del área: no entra a Pendientes ni se puede validar hasta quitarle la marca. Sus controles, planes y tareas internos cuentan en la proyección.</span>
    </span>
    <input type="hidden" name="interno" value="0" />
    <input type="checkbox" name="interno" value="1"
        {{ old('interno', $valor ?? false) ? 'checked' : '' }}
        class="sr-only peer" />
    <span class="relative w-11 h-6 shrink-0 bg-gray-200 rounded-full peer-checked:bg-violet-600 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
</label>
