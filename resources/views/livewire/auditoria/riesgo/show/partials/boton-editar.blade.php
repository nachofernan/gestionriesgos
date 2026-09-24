{{-- Botón del encabezado de un bloque: "Editar" si el cambio se aplica al guardar, "Proponer cambio" si queda pendiente. --}}
@if($modo === 'directo')
    <button type="button" wire:click="activarEdicion" wire:loading.attr="disabled" wire:target="activarEdicion"
        class="inline-flex items-center gap-1.5 text-[11px] font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1.5 rounded-lg transition-colors disabled:opacity-60">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 11l6.232-6.232a2.5 2.5 0 113.536 3.536L12.536 14.5H9V11z"/></svg>
        Editar
    </button>
@else
    <button type="button" wire:click="activarEdicion" wire:loading.attr="disabled" wire:target="activarEdicion"
        class="inline-flex items-center gap-1.5 text-[11px] font-bold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-2.5 py-1.5 rounded-lg transition-colors disabled:opacity-60">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.83L3 20l1.4-3.72A7.96 7.96 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        Proponer cambio
    </button>
@endif
