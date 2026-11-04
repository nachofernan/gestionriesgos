@props(['activo' => false, 'size' => 'normal'])
{{-- Marca de borrador interno (D-044): gestión propia del área, fuera del circuito de validación. --}}
@if($activo)
    @php
        $sz = match($size) {
            'xs'    => 'px-1.5 py-0.5 text-[9px]',
            'sm'    => 'px-2 py-0.5 text-[10px]',
            default => 'px-2.5 py-0.5 text-xs',
        };
    @endphp
    <span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 $sz rounded-full font-bold bg-violet-100 text-violet-700"]) }}
          title="Borrador interno: gestión propia del área, no entra a Pendientes">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        Interno
    </span>
@endif
