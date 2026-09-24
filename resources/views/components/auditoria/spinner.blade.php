{{-- Spinner chico para estados de carga (wire:loading / Alpine). Hereda el color del texto. --}}
<svg {{ $attributes->merge(['class' => 'animate-spin h-4 w-4']) }} fill="none" viewBox="0 0 24 24" aria-hidden="true">
    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
</svg>
