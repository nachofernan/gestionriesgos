{{--
    Campos opcionales del comité que dictamina una aprobación (D-039).
    Dentro de un componente Livewire se enlazan a `$prop` (por defecto 'comite');
    con $livewire = false salen como inputs de formulario `comite[numero]` / `comite[fecha]`.
--}}
@php
    $prop = $prop ?? 'comite';
    $livewire = $livewire ?? true;
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">N° de comité</label>
        <input type="text" maxlength="20" placeholder="Opcional"
               @if($livewire) wire:model="{{ $prop }}.numero" @else name="comite[numero]" value="{{ old('comite.numero') }}" @endif
               class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
        @error($prop.'.numero') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Fecha del comité</label>
        <input type="date" max="{{ now()->toDateString() }}"
               @if($livewire) wire:model="{{ $prop }}.fecha" @else name="comite[fecha]" value="{{ old('comite.fecha') }}" @endif
               class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
        @error($prop.'.fecha') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
